<?php
declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\Service;

use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mime\Part\DataPart;

class ExportMailerService
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly string $projectDir
    ) {
    }

    public function sendMailWithAttachment(string $filePath, string $exportName, bool $internal = true): void
    {
        if ($internal) {
            $emailAddress = $this->systemConfigService->get(
                'PhallosanCustomizations.config.internalExportEmail'
            );
        } else {
            $emailAddress = $this->systemConfigService->get(
                'PhallosanCustomizations.config.taxOfficeEmail'
            );
        }

        if (\is_array($emailAddress)) {
            return;
        }

        $emailAddress = (string) $emailAddress;

        $context = Context::createDefaultContext();

        $criteria = (new Criteria())
            ->addAssociation('mailTemplateType')
            ->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'phallosan.data_export'))
            ->setLimit(1);

        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();
        \assert($mailTemplate instanceof MailTemplateEntity);

        $salesChannelCriteria = new Criteria();
        $salesChannelCriteria->setLimit(1);
        $salesChannel = $this->salesChannelRepository->search($salesChannelCriteria, $context)->first();
        \assert($salesChannel instanceof SalesChannelEntity);

        $absoluteFilePath = $this->projectDir . '/public/' . $filePath;

        if (!file_exists($absoluteFilePath)) {
            return;
        }


        $file = (string)file_get_contents($absoluteFilePath);

        $attachment = new DataPart(
            $file,
            basename($absoluteFilePath),
            'text/csv'
        );

        $this->mailService->send([
            'recipients' => [
                $emailAddress => null,
            ],
            'senderName' => $mailTemplate->getTranslation('senderName'),
            'salesChannelId' => $salesChannel->getId(),
            'templateId' => $mailTemplate->getId(),
            'customFields' => $mailTemplate->getCustomFields(),
            'contentHtml' => $mailTemplate->getTranslation('contentHtml'),
            'contentPlain' => $mailTemplate->getTranslation('contentPlain'),
            'subject' => $mailTemplate->getTranslation('subject'),
            'attachments' => [
                $attachment,
            ],
        ], $context, [
            'exportName' => $exportName,
            'salesMonth' => (new \DateTime())->format('Y-m'),
        ]);
    }
}
