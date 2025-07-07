<?php

// config/symptoms_config.php

return [
    'categories' => [
        'mood' => 'حالت روحی',
        'aches' => 'دردهای جسمی',
        'digestion' => 'گوارش',
        'skin' => 'پوست',
        'sleep' => 'خواب',
        'libido' => 'میل جنسی',
        'discharge' => 'ترشحات',
        // Add more categories here
    ],
    'symptoms' => [
        'mood' => [
            'happy' => 'خوشحال 😊',
            'sad' => 'ناراحت 😔',
            'irritable' => 'تحریک‌پذیر 😠',
            'anxious' => 'مضطرب 😟',
            'calm' => 'آرام 😌',
            'energetic' => 'پرانرژی ⚡️',
            'tired' => 'خسته 😴',
        ],
        'aches' => [
            'headache' => 'سردرد 🤕',
            'cramps' => 'دل‌درد/کرامپ قاعدگی 😖',
            'backache' => 'کمردرد 뻐', // Note: Korean emoji, consider alternatives if issue
            'breast_pain' => 'درد سینه 🍈',
            'joint_pain' => 'درد مفاصل 🦴',
        ],
        'digestion' => [
            'bloating' => 'نفخ 💨',
            'constipation' => 'یبوست 🧱',
            'diarrhea' => 'اسهال 🚽',
            'nausea' => 'تهوع 🤢',
        ],
        'skin' => [
            'acne' => 'آکنه/جوش 뾰루지', // Note: Korean emoji
            'oily_skin' => 'پوست چرب ✨',
            'dry_skin' => 'پوست خشک 🌵',
        ],
        'sleep' => [
            'good_sleep' => 'خواب خوب 😴',
            'insomnia' => 'بی‌خوابی 뜬눈', // Note: Korean emoji
            'disturbed_sleep' => 'خواب آشفته 😵',
        ],
        'libido' => [
            'high_libido' => 'میل جنسی بالا 🔥',
            'low_libido' => 'میل جنسی پایین 🧊',
        ],
        'discharge' => [
            'none' => 'بدون ترشح 🚫',
            'clear_watery' => 'شفاف/آبکی 💧',
            'creamy_milky' => 'کرمی/شیری 🥛',
            'thick_sticky' => 'غلیظ/چسبناک 🍯',
            'spotting' => 'لکه‌بینی 🩸',
        ],
    ],
    // Helper to get a flat list of all symptom keys for validation if needed
    'all_symptom_keys' => function() {
        $keys = [];
        foreach (self::$symptoms as $category => $symptoms) {
            foreach ($symptoms as $key => $label) {
                $keys[] = $category . '_' . $key;
            }
        }
        return $keys;
    }
];
?>
