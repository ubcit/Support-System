<?php

use Modules\Attachments\Enums\AttachmentType;

test('AttachmentType::fromMimeType maps known MIME types', function (): void {
    $cases = [
        // Image
        ['image/jpeg', AttachmentType::Image],
        ['image/png', AttachmentType::Image],

        // Audio -> voice
        ['audio/ogg', AttachmentType::Voice],
        ['audio/mpeg', AttachmentType::Voice],

        // Video
        ['video/mp4', AttachmentType::Video],

        // PDF
        ['application/pdf', AttachmentType::Pdf],

        // Document
        ['application/msword', AttachmentType::Document],
        ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', AttachmentType::Document],
        ['text/plain', AttachmentType::Document],
        ['text/rtf', AttachmentType::Document],

        // Spreadsheet
        ['application/vnd.ms-excel', AttachmentType::Spreadsheet],
        ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', AttachmentType::Spreadsheet],
        ['text/csv', AttachmentType::Spreadsheet],

        // Archive
        ['application/zip', AttachmentType::Archive],
        ['application/x-rar-compressed', AttachmentType::Archive],
        ['application/x-7z-compressed', AttachmentType::Archive],
        ['application/gzip', AttachmentType::Archive],

        // Unknown
        ['application/x-unknown', AttachmentType::Other],
    ];

    foreach ($cases as [$mimeType, $expected]) {
        expect(AttachmentType::fromMimeType($mimeType))->toBe($expected);
    }
});

