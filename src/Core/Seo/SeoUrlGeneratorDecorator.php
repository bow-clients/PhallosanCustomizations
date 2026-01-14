<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Seo;

use PhallosanCustomizations\PhallosanConstants;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

class SeoUrlGeneratorDecorator extends SeoUrlGenerator
{
    private readonly TwigVariableParser $twigVariableParser;

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RouterInterface            $router,
        private readonly RequestStack               $requestStack,
        private readonly Environment                $twig,
        TwigVariableParserFactory                   $parserFactory,
        private readonly LoggerInterface            $logger,
        private readonly SystemConfigService        $systemConfigService,
    ) {
        parent::__construct(
            $definitionRegistry,
            $router,
            $requestStack,
            $twig,
            $parserFactory,
            $logger
        );

        $this->twigVariableParser = $parserFactory->getParser($twig);
    }

    public function generate(
        array $ids,
        string $template,
        SeoUrlRouteInterface $route,
        Context $context,
        SalesChannelEntity
        $salesChannel
    ): iterable {
        $criteria = new Criteria($ids);
        $route->prepareCriteria($criteria, $salesChannel);

        $config = $route->getConfig();

        $repository = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

        if ($this->loadTwigTemplate($config, $template)) {
            $associations = $this->getAssociations($template, $repository->getDefinition());
            $criteria->addAssociations($associations);
            $criteria->addAssociation('translations');
            $criteria->addAssociation('categories.translations');
            $criteria->addAssociation('category.translations');

            $criteria->setLimit(50);

            /** @var RepositoryIterator<LandingPageCollection|CategoryCollection|ProductCollection> $iterator */
            $iterator = $context->enableInheritance(static fn (Context $context): RepositoryIterator => new RepositoryIterator($repository, $context, $criteria));

            while ($searchResult = $iterator->fetch()) {
                yield from $this->generateUrls($route, $config, $salesChannel, $searchResult, $this->getTemplateName($template));
            }
        }
    }

    public function loadTwigTemplate(SeoUrlRouteConfig $config, string $template): bool
    {
        $templateName = $this->getTemplateName($template);
        $template = '{% autoescape \'' . self::ESCAPE_SLUGIFY . "' %}$template{% endautoescape %}";
        $this->twig->setLoader(new ChainLoader([
            new ArrayLoader([$templateName => $template]),
            $this->twig->getLoader(),
        ]));

        try {
            $this->twig->loadTemplate($this->twig->getTemplateClass($templateName), $templateName);
        } catch (SyntaxError $syntaxError) {
            $this->logger->warning('Error initializing SEO URL template', [
                'exception' => $syntaxError,
                'template' => $template,
                'template_name' => $templateName,
            ]);

            if (!$config->getSkipInvalid()) {
                throw SeoException::invalidTemplate('Syntax error: ' . $syntaxError->getMessage());
            }

            return false;
        }

        return true;
    }

    private function generateUrls(
        SeoUrlRouteInterface $seoUrlRoute,
        SeoUrlRouteConfig $config,
        SalesChannelEntity $salesChannel,
        EntitySearchResult $searchResult,
        string $templateName
    ): iterable {
        $request = $this->requestStack->getMainRequest();

        $basePath = $request ? $request->getBasePath() : '';

        $useNativeLang = false;

        $languages = $this->systemConfigService->get(PhallosanConstants::PLUGIN_CONFIG_SEO_URL_LANGUAGE);
        if (\in_array($salesChannel->getLanguageId(), (array) $languages, true)) {
            $useNativeLang = true;
        }


        foreach ($searchResult->getEntities() as $entity) {
            $seoUrl = new SeoUrlEntity();
            $seoUrl->setForeignKey($entity->getUniqueIdentifier());

            $seoUrl->setIsCanonical(true);
            $seoUrl->setIsModified(false);
            $seoUrl->setIsDeleted(false);

            $copy = clone $seoUrl;

            if ($useNativeLang) {
                $translationKey = $entity->getUniqueIdentifier() . '-' . Defaults::LANGUAGE_SYSTEM;
                /** @phpstan-ignore-next-line */
                $translation = $entity->getTranslations()->get($translationKey);
                $translation = $translation->jsonSerialize();
                $entity->setTranslated($translation);
                if (method_exists($entity, 'setName')) {
                    $entity->setName($translation['name']);
                }

                if ($entity instanceof ProductEntity) {
                    $categories = $entity->getCategories();
                    if ($categories) {
                        foreach ($categories as $category) {
                            $translationKey = $category->getId() . '-' . Defaults::LANGUAGE_SYSTEM;
                            $translation = $category->getTranslations()?->get($translationKey);
                            if ($translation) {
                                $translation = $translation->jsonSerialize();
                                $category->setTranslated($translation);
                            }
                        }
                    }
                }
            }

            $mapping = $seoUrlRoute->getMapping($entity, $salesChannel);

            $copy->setError($mapping->getError());

            $pathInfo = $this->router->generate($config->getRouteName(), $mapping->getInfoPathContext());
            $pathInfo = $this->removePrefix($pathInfo, $basePath);

            $copy->setPathInfo($pathInfo);

            $seoPathInfo = $this->getSeoPathInfo($mapping, $config, $templateName);

            if ($seoPathInfo === null || $seoPathInfo === '') {
                continue;
            }

            $copy->setSeoPathInfo($seoPathInfo);
            $copy->setSalesChannelId($salesChannel->getId());

            yield $copy;
        }
    }

    private function getSeoPathInfo(SeoUrlMapping $mapping, SeoUrlRouteConfig $config, string $templateName): ?string
    {
        try {
            return trim($this->twig->render($templateName, $mapping->getSeoPathInfoContext()));
        } catch (Error $error) {
            $this->logger->warning('Error received on rendering SEO URL template', [
                'exception' => $error,
                'mapping_entity_type' => \get_class($mapping->getEntity()),
                'mapping_error' => $mapping->getError(),
                'mapping_info_path' => $mapping->getInfoPathContext(),
                'mapping' => $mapping,
            ]);

            if (!$config->getSkipInvalid()) {
                throw SeoException::invalidTemplate('Error: ' . $error->getMessage());
            }

            return null;
        }
    }

    private function getTemplateName(string $template): string
    {
        return 'seo_url_template_' . Hasher::hash($template);
    }

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }

    private function getAssociations(string $template, EntityDefinition $definition): array
    {
        try {
            $variables = $this->twigVariableParser->parse($template);
        } catch (\Exception $e) {
            throw SeoException::invalidTemplate($e->getMessage());
        }

        $associations = [];
        foreach ($variables as $variable) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $variable, true);

            $lastField = end($fields);

            $runtime = new Runtime();

            if ($lastField && $lastField->getFlag(Runtime::class)) {
                $associations = array_merge($associations, $runtime->getDepends());
            }

            $associations[] = EntityDefinitionQueryHelper::getAssociationPath($variable, $definition);
        }

        return array_filter(array_unique($associations));
    }
}
