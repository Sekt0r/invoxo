<?php

return [
    'default' => [
        'registration_number' => [
            'label' => 'Registration number',
            'hint' => 'Company register entry / file number.',
        ],
        'tax_identifier' => [
            'label' => 'Tax identifier',
            'hint' => 'National tax identification number (not VAT ID).',
        ],
    ],
    'RO' => [
        'registration_number' => [
            'label' => 'Nr. Reg. Com. (J…)',
            'hint' => 'Example: J12/123/2020',
        ],
        'tax_identifier' => [
            'label' => 'CUI',
            'hint' => 'Fiscal identification number (CUI). VAT ID is RO+CUI if VAT registered.',
        ],
    ],
    'EE' => [
        'registration_number' => [
            'label' => 'Registry code',
            'hint' => 'Commercial register entry number.',
        ],
        'tax_identifier' => [
            'label' => 'Registry code',
            'hint' => 'Used as tax identifier in Estonia. VAT ID is EE+code if VAT registered.',
        ],
    ],
    'DE' => [
        'registration_number' => [
            'label' => 'Handelsregister number (HRB/HRA)',
            'hint' => 'Commercial register entry number.',
        ],
        'tax_identifier' => [
            'label' => 'Steuernummer',
            'hint' => 'National tax number (not VAT ID).',
        ],
    ],
    'FR' => [
        'registration_number' => [
            'label' => 'SIREN',
            'hint' => 'Company registration number.',
        ],
        'tax_identifier' => [
            'label' => 'SIREN/SIRET',
            'hint' => 'National business identifier.',
        ],
    ],
];





