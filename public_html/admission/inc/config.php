<?php
declare(strict_types=1);

return [
    'site' => [
        'name' => 'Find Pilates',
        'club_name' => 'ファインドスポーツクラブ',
        'company_name' => '鈴木ヘルスセンター株式会社',
        'postal_code' => '329-2754',
        'address' => '栃木県那須塩原市西大和1-8 そすいスクエアAQUAS 2F',
        'tel' => '0287-36-0419',
        'email' => 'findsportsclub@outlook.jp',
        'url' => 'https://findpilates.jp/',
    ],

    'mail' => [
        'admin_to' => 'findsportsclub@outlook.jp',
        'from' => 'findsportsclub@outlook.jp',
        'from_name' => 'Find Pilates 入会受付',
        'admin_subject_prefix' => '【入会受付】Find Pilates Web申込',
        'user_subject' => '【Find Pilates】入会受付を承りました',
    ],

    'admin' => [
        'storage_file' => __DIR__ . '/../tmp/admissions.json',
        'campaigns_file' => __DIR__ . '/../tmp/campaigns.json',
        'photo_archive_dir' => dirname(__DIR__, 3) . '/storage/admission_photos/archive',
        'status_options' => [
            'new' => '未対応',
            'contacting' => '連絡中',
            'scheduled' => '手続き予約済み',
            'registered' => 'SLIM登録済み',
        ],
    ],

    'fees' => [
        'join_fee' => 11000,
        'processing_fee' => 0,
    ],

    'date_selection' => [
        'min_date' => '2026-06-30',
    ],

    'campaign' => [
        'enabled' => false,
        'months' => [7, 8],
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-31',
        'combinable' => false,
        'join_fee' => 0,
        'single_initial_fee' => 0,
        'addon_initial_fees' => [
            8 => 3850,
            16 => 7700,
        ],
    ],

    'pilates_courses' => [
        'basic' => [
            'label' => 'ベーシック会員',
            'monthly_fee' => 8800,
            'monthly_visits' => 8,
            'description' => '月8回、4つのメニューから自由に選択',
        ],
        'double' => [
            'label' => 'ダブル会員',
            'monthly_fee' => 12650,
            'monthly_visits' => 16,
            'description' => '月16回、4つのメニューから自由に選択',
        ],
        // 旧URLや保存済みデータとの互換用。画面表示はダブル会員に統一します。
        'pilates_plus' => [
            'label' => 'ダブル会員',
            'monthly_fee' => 12650,
            'monthly_visits' => 16,
            'description' => '月16回、4つのメニューから自由に選択',
        ],
        'pilates' => [
            'label' => 'ベーシック会員',
            'monthly_fee' => 8800,
            'monthly_visits' => 8,
            'description' => '月8回、4つのメニューから自由に選択',
        ],
    ],

    'menu_options' => [
        'インストラクター指導',
        '映像レッスン',
        'セルフエステマシン利用',
        'ピラティスマシンセルフ利用',
    ],

    'main_club_memberships' => [
        'find_master' => [
            'category' => 'full',
            'label' => 'ファインドマスター',
            'monthly_fee' => 11550,
            'description' => '営業時間内にジム・スタジオ・プールを幅広く利用できます。',
        ],
        'day_free' => [
            'category' => 'time',
            'label' => 'デイフリー会員',
            'monthly_fee' => 9900,
            'description' => '主に日中の時間帯に利用したい方向けです。',
        ],
        'night_holiday' => [
            'category' => 'time',
            'label' => 'ナイト＆ホリデー会員',
            'monthly_fee' => 10450,
            'description' => '夜間・土日祝を中心に利用したい方向けです。',
        ],
        'night_holiday_u34' => [
            'category' => 'time',
            'label' => 'ナイト＆ホリデー会員（34才以下）',
            'monthly_fee' => 8030,
            'description' => '34才以下で夜間・土日祝を中心に利用したい方向けです。',
        ],
        'gym_free' => [
            'category' => 'area',
            'label' => 'ジムフリー会員',
            'monthly_fee' => 6930,
            'description' => 'ジムエリアを利用できます。',
        ],
        'gym_pool' => [
            'category' => 'area',
            'label' => 'ジム・プール会員',
            'monthly_fee' => 8580,
            'description' => 'ジムとプールを利用できます。',
        ],
        'gym_studio' => [
            'category' => 'area',
            'label' => 'ジム・スタジオ会員',
            'monthly_fee' => 10230,
            'description' => 'ジムとスタジオを利用できます。',
        ],
    ],

    'main_club_categories' => [
        'full' => 'FULL',
        'time' => '利用時間で選ぶ',
        'area' => '利用エリアで選ぶ',
    ],

    'pilates_addons' => [
        'basic' => [
            'label' => 'ピラティスベーシック会員',
            'add_fee' => 3850,
            'monthly_visits' => 8,
            'description' => '4つのメニューから自由に月8回利用',
        ],
        'double' => [
            'label' => 'ピラティスダブル会員',
            'add_fee' => 7700,
            'monthly_visits' => 16,
            'description' => '4つのメニューから自由に月16回利用',
        ],
        // 旧保存値との互換用。
        'master' => [
            'label' => 'ピラティスダブル会員',
            'add_fee' => 7700,
            'monthly_visits' => 16,
            'description' => '4つのメニューから自由に月16回利用',
        ],
    ],

    'initial_visit_options' => [
        8 => [2, 4, 6, 8],
        16 => [4, 8, 12, 16],
    ],

    'procedure_time_slots_weekday' => [
        '' => '指定なし',
        '10:00-11:30' => '10:00〜11:30',
        '11:30-13:00' => '11:30〜13:00',
        '13:00-14:30' => '13:00〜14:30',
        '14:30-16:00' => '14:30〜16:00',
        '16:00-17:30' => '16:00〜17:30',
        '17:30-19:00' => '17:30〜19:00',
        '19:00-20:00' => '19:00〜20:00',
    ],
    'procedure_time_slots_sunday' => [
        '' => '指定なし',
        '10:00-11:30' => '10:00〜11:30',
        '11:30-13:00' => '11:30〜13:00',
        '13:00-14:30' => '13:00〜14:30',
        '14:30-16:00' => '14:30〜16:00',
    ],
    'procedure_time_slots' => [
        '' => '指定なし',
        '10:00-11:30' => '10:00〜11:30',
        '11:30-13:00' => '11:30〜13:00',
        '13:00-14:30' => '13:00〜14:30',
        '14:30-16:00' => '14:30〜16:00',
        '16:00-17:30' => '16:00〜17:30',
        '17:30-19:00' => '17:30〜19:00',
        '19:00-20:00' => '19:00〜20:00',
    ],
    'closed_dates' => [],
    'closed_day_rules' => [],
    'require_second_third_preferences' => false,

    'health_checks' => [
        'no_exercise_restriction' => [
            'label' => '医師から運動を禁止・制限されていません。',
            'note' => '該当する場合は事前に医師の許可を得て、補足欄へご記入ください。',
            'required' => true,
        ],
        'no_infectious_disease' => [
            'label' => '他の方へ感染するおそれのある疾患・症状はありません。',
            'note' => '',
            'required' => true,
        ],
        'no_sudden_risk' => [
            'label' => '現在、てんかん、心疾患、その他、運動中に発作、失神、意識消失または急激な体調悪化を生じるおそれのある疾患・症状はありません。',
            'note' => '',
            'required' => true,
        ],
        'not_pregnant' => [
            'label' => '妊娠中ではありません。',
            'note' => '該当しない方も確認のためチェックしてください。',
            'required' => true,
        ],
        'no_membership_disqualification' => [
            'label' => '入会資格に抵触する事項はありません。',
            'note' => '刺青・反社会的勢力との関係など、会則に定める事項を含みます。',
            'required' => true,
        ],
        'agree_self_management' => [
            'label' => '施設利用中は案内を守り、自身の体調管理に責任を持って利用します。',
            'note' => '',
            'required' => true,
        ],
    ],

    'procedure_info' => [
        'things_to_do' => [
            '本人確認',
            '月会費の口座登録',
            '予約方法と利用案内',
        ],
        'things_to_bring' => [
            '銀行キャッシュカード、または通帳番号・通帳印',
            '免許証または保険証などの身分証明書',
        ],
        'contact_notice' => '内容確認後、3営業日以内に店頭手続き希望日の確認・調整についてご連絡いたします。',
    ],

    'photo' => [
        'required' => true,
        'upload_max_bytes' => 5 * 1024 * 1024,
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'tmp_dir' => dirname(__DIR__, 3) . '/storage/admission_photos/tmp',
    ],

    'texts' => [
        'price_notice' => '表示金額は現時点の概算です。初月会費と初月利用回数は利用開始希望日から自動計算します。正式金額は店頭手続き時に確定します。',
        'start_date_help' => 'ご利用を始めたい日です。初月の利用回数は利用開始希望日から自動計算します。',
        'procedure_date_help' => '本人確認・口座登録・利用案内を行うための来店希望日です。',
        'addon_help' => '本館会員の月会費に追加して、Find Pilatesの利用回数を付ける項目です。4つのメニューから自由に選べます。',
    ],
];
