<?php

namespace Modules\Attachments\Enums;

enum AttachmentType: string
{
    case Image = 'image';
    case Voice = 'voice';
    case Pdf = 'pdf';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Video = 'video';
    case Archive = 'archive';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Voice => 'Voice',
            self::Pdf => 'PDF',
            self::Document => 'Document',
            self::Spreadsheet => 'Spreadsheet',
            self::Video => 'Video',
            self::Archive => 'Archive',
            self::Other => 'Other',
        };
    }

    /**
     * Determine the attachment type from a MIME type string.
     */
    public static function fromMimeType(string $mimeType): self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'audio/') => self::Voice,
            str_starts_with($mimeType, 'video/') => self::Video,
            $mimeType === 'application/pdf' => self::Pdf,
            in_array($mimeType, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'text/rtf',
            ]) => self::Document,
            in_array($mimeType, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
            ]) => self::Spreadsheet,
            in_array($mimeType, [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/gzip',
            ]) => self::Archive,
            default => self::Other,
        };
    }
}
