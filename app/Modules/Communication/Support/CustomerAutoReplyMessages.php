<?php

namespace Modules\Communication\Support;

class CustomerAutoReplyMessages
{
    /**
     * @var array<string, array{en: string, ar: string}>
     */
    private const TEMPLATES = [
        'session_completed' => [
            'en' => "Hi {name}, we're happy to let you know that your request has been completed and is ready. If you need anything else, feel free to reach out. Thank you!",
            'ar' => 'مرحباً {name}، يسعدنا إبلاغك بأن طلبك قد اكتمل وأصبح جاهزاً. إذا احتجت أي شيء آخر، لا تتردد في التواصل معنا. شكراً لك!',
        ],
        'session_processing' => [
            'en' => 'We received your request and it is now being processed.',
            'ar' => 'تم استلام طلبك وهو قيد المعالجة الآن.',
        ],
        'project_verification_prompt' => [
            'en' => 'Please verify yourself by entering your project code.',
            'ar' => 'يرجى التحقق من هويتك بإدخال رمز المشروع.',
        ],
        'project_verification_invalid' => [
            'en' => 'That project code was not recognized. Please check the code and try again.',
            'ar' => 'رمز المشروع غير معروف. يرجى التحقق من الرمز والمحاولة مرة أخرى.',
        ],
        'project_verification_ok_awaiting_request' => [
            'en' => 'Verified for project {project}. Please send your request.',
            'ar' => 'تم التحقق لمشروع {project}. يرجى إرسال طلبك.',
        ],
    ];

    /**
     * @param  array<string, string|int|float|null>  $replacements
     */
    public static function body(string $key, string $locale, array $replacements = []): string
    {
        $locale = CustomerLocale::normalize($locale);
        $template = self::TEMPLATES[$key][$locale]
            ?? self::TEMPLATES[$key][CustomerLocale::EN]
            ?? '';

        foreach ($replacements as $name => $value) {
            $template = str_replace('{'.$name.'}', (string) ($value ?? ''), $template);
        }

        return $template;
    }
}
