<?php
// config/plans.php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing display settings
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'currency' => 'EUR',
        'yearly_multiplier' => 10,
        'refund_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Highlighted plan (single source)
    |--------------------------------------------------------------------------
    */
    'highlight' => 'pro',

    /*
    |--------------------------------------------------------------------------
    | Permission catalog (single source of truth for labels)
    |--------------------------------------------------------------------------
    | Every permission exists here.
    | Upcoming features simply include "(coming soon)" in the label.
    */
    'permissions' => [
        'single_company' => 'Single company included',
        'eu_templates' => 'EU invoice templates',
        'vat_id_format_check' => 'VAT ID format check',
        'manual_vat_rates' => 'Manual VAT rates',
        'pdf_export' => 'PDF export',

        'multi_currency' => 'Multi-currency support', // TODO
        'vies_validation' => 'VIES VAT validation', // OK
        'vat_rate_auto' => 'Automatic VAT rate detection', // OK
        'cross_border_b2b' => 'Cross-border EU B2B logic', // OK
        'accountant_exports' => 'Accountant-ready exports', // TODO: csv

        'priority_support' => 'Priority support',
        'audit_trail' => 'Audit trail (coming soon)',
        'peppol' => 'Peppol e-invoicing (coming soon)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan order (used for inheritance)
    |--------------------------------------------------------------------------
    */
    'order' => [
        'starter',
        'pro',
        'business',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    | permissions[key] = true → granted AND displayed
    | permissions[key] = false → granted but hidden (inherited)
    | key absence → not granted
    */
    'plans' => [

        'starter' => [
            'name' => 'Starter',
            'price_monthly' => 19,
            'highlight' => false,

            'permissions' => [
                'single_company' => true,
                'eu_templates' => true,
                'vat_id_format_check' => true,
                'manual_vat_rates' => true,
                'pdf_export' => true,
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'price_monthly' => 39,
            'highlight' => true,
            'badge' => 'Recommended',

            'permissions' => [
                // new, displayed
                'multi_currency' => true,
                'vies_validation' => true,
                'vat_rate_auto' => true,
                'cross_border_b2b' => true,
                'accountant_exports' => true,
            ],
        ],

        'business' => [
            'name' => 'Business',
            'price_monthly' => 79,
            'highlight' => false,

            'permissions' => [
                // old, hidden
                'multi_currency' => false,
                'vies_validation' => false,
                'vat_rate_auto' => false,
                'cross_border_b2b' => false,
                'accountant_exports' => false,

                // new, displayed
                'priority_support' => true,
                'audit_trail' => true,
                'peppol' => true,
            ],

            'disclaimer' => 'Planned features. Availability may vary by country.',
        ],
    ],
];
