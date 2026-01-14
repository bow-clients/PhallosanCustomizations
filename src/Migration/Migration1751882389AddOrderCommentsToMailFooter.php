<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1751882389AddOrderCommentsToMailFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751882389;
    }

    public function update(Connection $connection): void
    {
        $orderCommentsHTML = '{% if order and order.extensions.orderComments is defined %}
<div>
    {% for orderComment in order.extensions.orderComments %}
        <div style="font-family:arial; font-size:12px; color:#2B3136;">
            <p>
                {{ orderComment.comment|nl2br }}
            </p>
        </div>
    {% endfor %}
    <br/>
</div>
{% endif %}
';
        $orderCommentsPlain = '{% if order and order.extensions.orderComments is defined %}

    {% for orderComment in order.extensions.orderComments %}

{{ orderComment.comment }}
    {% endfor %}
    
{% endif %}
';

        // Get all footer entries to update them safely
        $footers = $connection->fetchAllAssociative(
            'SELECT mail_header_footer_id, language_id, footer_html, footer_plain FROM mail_header_footer_translation'
        );

        // Update each footer entry using the safer update method
        foreach ($footers as $footer) {
            $currentHtml = $footer['footer_html'] ?? '';
            $currentPlain = $footer['footer_plain'] ?? '';

            $connection->update(
                'mail_header_footer_translation',
                [
                    'footer_html' => $orderCommentsHTML . $currentHtml,
                    'footer_plain' => $orderCommentsPlain . $currentPlain,
                ],
                [
                    'mail_header_footer_id' => $footer['mail_header_footer_id'],
                    'language_id' => $footer['language_id'],
                ]
            );
        }
    }
}
