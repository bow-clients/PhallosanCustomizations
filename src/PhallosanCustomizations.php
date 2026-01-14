<?php declare(strict_types=1);

namespace PhallosanCustomizations;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;

class PhallosanCustomizations extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->createMailTemplate($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->createMailTemplate($updateContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // Remove or deactivate the data created by the plugin
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        // Deactivate entities, such as a new payment method
        // Or remove previously created entities
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    private function createMailTemplate(Context $context): void
    {
        if (!$this->container) {
            return;
        }

        $mailTemplateTypeRepository = $this->container->get('mail_template_type.repository');
        \assert($mailTemplateTypeRepository instanceof EntityRepository);

        $mailTemplateRepository = $this->container->get('mail_template.repository');
        \assert($mailTemplateRepository instanceof EntityRepository);

        $typeCriteria = new Criteria();
        $typeCriteria->addFilter(new EqualsFilter('technicalName', 'phallosan.data_export'));

        $existingType = $mailTemplateTypeRepository->search($typeCriteria, $context)->first();

        if (!$existingType) {
            $templateTypeId = Uuid::randomHex();
        } else {
            \assert($existingType instanceof MailTemplateTypeEntity);
            $templateTypeId = $existingType->getId();
        }

        $mailTemplateTypeRepository->upsert([[
            'id' => $templateTypeId,
            'technicalName' => 'phallosan.data_export',
            'name' => 'Sales/Cancellations Export',
            'availableEntities' => [],
        ]], $context);

        $templateCriteria = new Criteria();
        $templateCriteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'phallosan.data_export'));

        $existingTemplate = $mailTemplateRepository->search($templateCriteria, $context)->first();

        if (!$existingTemplate) {
            $templateId = Uuid::randomHex();
        } else {
            \assert($existingTemplate instanceof MailTemplateEntity);
            $templateId = $existingTemplate->getId();
        }

        $mailTemplateRepository->upsert([[
            'id' => $templateId,
            'mailTemplateTypeId' => $templateTypeId,
            'subject' => '{{ exportName }} - {{ salesMonth }}',
            'contentPlain' => 'Attached is the {{ exportName }} for {{ salesMonth }}.',
            'contentHtml' => '<p>Attached is the {{ exportName }} for {{ salesMonth }}.</p>',
            'senderName' => 'Phallosan',
            'senderEmail' => 'no-reply@phallosan.de',
        ]], $context);
    }
}
