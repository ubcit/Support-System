<?php

namespace Modules\Communication\Support;

use Modules\Attachments\Enums\AttachmentType;

/**
 * Helpers to build Meta WhatsApp Cloud API shaped inbound payloads.
 *
 * Used by:
 * - feature tests (sync, faked HTTP)
 * - CLI + Livewire simulators (either real webhook shape or local simulated media)
 */
final class WhatsAppInboundPayload
{
    private function __construct() {}

    public static function inferWhatsAppMediaType(string $mimeType): string
    {
        // WhatsApp uses "image|audio|video|document" for the `type` field,
        // while we categorize content as image/voice/video/pdf/document/etc elsewhere.
        return match (AttachmentType::fromMimeType($mimeType)) {
            AttachmentType::Image => 'image',
            AttachmentType::Voice => 'audio',
            AttachmentType::Video => 'video',
            default => 'document',
        };
    }

    /**
     * @param  array{cooldown_seconds?: int, force_ai_error?: bool, ai_provider?: string}  $simControls
     * @return array<string, mixed>
     */
    public static function text(
        string $fromPhone,
        string $profileName,
        string $body,
        ?string $messageId = null,
        array $simControls = [],
    ): array {
        $messageId ??= 'wamid.'.uniqid();

        $value = [
            'messaging_product' => 'whatsapp',
            'metadata' => [
                'display_phone_number' => '15550000000',
                'phone_number_id' => '000000000000000',
            ],
            'contacts' => [
                [
                    'profile' => ['name' => $profileName],
                    'wa_id' => $fromPhone,
                ],
            ],
            'messages' => [
                [
                    'from' => $fromPhone,
                    'id' => $messageId,
                    'timestamp' => (string) time(),
                    'type' => 'text',
                    'text' => ['body' => $body],
                ],
            ],
        ];

        if ($simControls !== []) {
            $value['sim_controls'] = $simControls;
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '000000000000000',
                    'changes' => [
                        [
                            'value' => $value,
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $aiControls
     * @param  array{cooldown_seconds?: int, force_ai_error?: bool, ai_provider?: string}  $simControls
     * @return array<string, mixed>
     */
    public static function media(
        string $fromPhone,
        string $profileName,
        string $mimeType,
        string $caption,
        string $filename,
        ?string $mediaId = null,
        ?string $messageId = null,
        ?string $simulatedPath = null,
        string $simulatedDisk = 'local',
        array $aiControls = [],
        array $simControls = [],
    ): array {
        $whatsAppType = self::inferWhatsAppMediaType($mimeType);

        $messageId ??= 'wamid.'.uniqid();
        $mediaId ??= 'media.'.uniqid();

        $media = [
            'id' => $mediaId,
            'mime_type' => $mimeType,
            'caption' => $caption,
            'filename' => $filename,
        ];

        if ($simulatedPath) {
            // When present, ProcessIncomingMessage can route the attachment
            // through the local simulated provider branch.
            $media['simulated_path'] = $simulatedPath;
            $media['disk'] = $simulatedDisk;
        }

        $value = [
            'messaging_product' => 'whatsapp',
            'ai_controls' => $aiControls,
            'metadata' => [
                'display_phone_number' => '15550000000',
                'phone_number_id' => '000000000000000',
            ],
            'contacts' => [
                [
                    'profile' => ['name' => $profileName],
                    'wa_id' => $fromPhone,
                ],
            ],
            'messages' => [
                [
                    'from' => $fromPhone,
                    'id' => $messageId,
                    'timestamp' => (string) time(),
                    'type' => $whatsAppType,
                    $whatsAppType => $media,
                ],
            ],
        ];

        if ($simControls !== []) {
            $value['sim_controls'] = $simControls;
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '000000000000000',
                    'changes' => [
                        [
                            'value' => $value,
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }
}
