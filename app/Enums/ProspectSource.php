<?php

namespace App\Enums;

enum ProspectSource: string
{
    case Import = 'import';
    case Manual = 'manual';
    case WebsiteForm = 'website_form';
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case Advertising = 'advertising';
    case Campaign = 'campaign';
    case Inquiry = 'inquiry';
    case Other = 'other';
    case Linkedin = 'linkedin';
    case Google = 'google';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Internal = 'internal';
    case Web = 'web';
    case BusinessProvider = 'business_provider';

    public function label(): string
    {
        return match ($this) {
            self::Import => __('prospect_source_import'),
            self::Manual => __('prospect_source_manual'),
            self::WebsiteForm => __('prospect_source_website_form'),
            self::Referral => __('prospect_source_referral'),
            self::SocialMedia => __('prospect_source_social_media'),
            self::Advertising => __('prospect_source_advertising'),
            self::Campaign => __('prospect_source_campaign'),
            self::Inquiry => __('prospect_source_inquiry'),
            self::Other => __('prospect_source_other'),
            self::Linkedin => __('prospect_source_linkedin'),
            self::Google => __('prospect_source_google'),
            self::Facebook => __('prospect_source_facebook'),
            self::Instagram => __('prospect_source_instagram'),
            self::Internal => __('prospect_source_internal'),
            self::Web => __('prospect_source_web'),
            self::BusinessProvider => __('prospect_source_business_provider'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
