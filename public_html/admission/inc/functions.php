<?php
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/repository.php';

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function post_string(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    return is_array($value) ? $value : [];
}

function yen(int|float $amount): string
{
    return number_format((int)round($amount)) . '円';
}

function date_label(?string $date): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $week = ['日', '月', '火', '水', '木', '金', '土'];
    return date('Y年n月j日', $timestamp) . '（' . $week[(int)date('w', $timestamp)] . '）';
}

function calculate_age(?string $birth): ?int
{
    if (!$birth) {
        return null;
    }

    try {
        $birthDate = new DateTimeImmutable($birth);
        $today = new DateTimeImmutable('today');
        return (int)$birthDate->diff($today)->y;
    } catch (Throwable) {
        return null;
    }
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function normalize_phone(string $phone): string
{
    return preg_replace('/[^\d]/', '', $phone) ?? '';
}

function normalize_postal_code(string $postalCode): string
{
    return preg_replace('/[^\d]/', '', $postalCode) ?? '';
}

function split_legacy_person_name(string $name): array
{
    $name = trim(preg_replace('/[\s　]+/u', ' ', $name) ?? $name);
    if ($name === '') {
        return ['', ''];
    }

    $parts = explode(' ', $name, 2);
    return [$parts[0] ?? '', $parts[1] ?? ''];
}

function admission_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return isset($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

function collect_form_data(array $post): array
{
    $postalCode = normalize_postal_code((string)($post['postal_code'] ?? ''));
    $phone = normalize_phone((string)($post['phone'] ?? ''));
    $emergencyPhone = normalize_phone((string)($post['emergency_phone'] ?? ''));
    $surname = trim((string)($post['surname'] ?? ''));
    $givenName = trim((string)($post['given_name'] ?? ''));
    $surnameKana = trim((string)($post['surname_kana'] ?? ''));
    $givenNameKana = trim((string)($post['given_name_kana'] ?? ''));
    $legacyName = trim((string)($post['name'] ?? ''));
    $legacyKana = trim((string)($post['kana'] ?? ''));

    if (($surname === '' || $givenName === '') && $legacyName !== '') {
        [$derivedSurname, $derivedGivenName] = split_legacy_person_name($legacyName);
        $surname = $surname !== '' ? $surname : $derivedSurname;
        $givenName = $givenName !== '' ? $givenName : $derivedGivenName;
    }

    if (($surnameKana === '' || $givenNameKana === '') && $legacyKana !== '') {
        [$derivedSurnameKana, $derivedGivenNameKana] = split_legacy_person_name($legacyKana);
        $surnameKana = $surnameKana !== '' ? $surnameKana : $derivedSurnameKana;
        $givenNameKana = $givenNameKana !== '' ? $givenNameKana : $derivedGivenNameKana;
    }

    $prefecture = trim((string)($post['prefecture'] ?? ''));
    $cityArea = trim((string)($post['city_area'] ?? ''));
    $streetAddress = trim((string)($post['street_address'] ?? ''));
    $building = trim((string)($post['building'] ?? ''));
    $legacyAddress = trim((string)($post['address'] ?? ''));
    $address = trim($prefecture . $cityArea . $streetAddress . ($building !== '' ? ' ' . $building : ''));
    $birth = trim((string)($post['birth'] ?? ''));
    $birthYear = trim((string)($post['birth_year'] ?? ''));
    $birthMonth = trim((string)($post['birth_month'] ?? ''));
    $birthDay = trim((string)($post['birth_day'] ?? ''));
    if ($birth === '' && $birthYear !== '' && $birthMonth !== '' && $birthDay !== '') {
        $birth = sprintf('%04d-%02d-%02d', (int)$birthYear, (int)$birthMonth, (int)$birthDay);
    }

    return [
        'use_type' => trim((string)($post['use_type'] ?? 'new')),
        'course' => trim((string)($post['course'] ?? '')),
        'main_member_status' => trim((string)($post['main_member_status'] ?? 'existing')),
        'main_member_number' => trim((string)($post['main_member_number'] ?? '')),
        'main_membership' => trim((string)($post['main_membership'] ?? '')),
        'addon' => trim((string)($post['addon'] ?? '')),
        'initial_visits' => trim((string)($post['initial_visits'] ?? '')),
        'start_date' => trim((string)($post['start_date'] ?? '')),
        'campaign_code' => trim((string)($post['campaign_code'] ?? '')),
        'procedure_date_1' => trim((string)($post['procedure_date_1'] ?? '')),
        'procedure_time_1' => trim((string)($post['procedure_time_1'] ?? '')),
        'procedure_date_2' => trim((string)($post['procedure_date_2'] ?? '')),
        'procedure_time_2' => trim((string)($post['procedure_time_2'] ?? '')),
        'procedure_date_3' => trim((string)($post['procedure_date_3'] ?? '')),
        'procedure_time_3' => trim((string)($post['procedure_time_3'] ?? '')),
        'surname' => $surname,
        'given_name' => $givenName,
        'surname_kana' => $surnameKana,
        'given_name_kana' => $givenNameKana,
        'name' => trim($surname . ' ' . $givenName),
        'kana' => trim($surnameKana . ' ' . $givenNameKana),
        'birth' => $birth,
        'birth_year' => $birthYear,
        'birth_month' => $birthMonth,
        'birth_day' => $birthDay,
        'school_confirmation' => trim((string)($post['school_confirmation'] ?? '')),
        'gender' => trim((string)($post['gender'] ?? '')),
        'phone_type' => trim((string)($post['phone_type'] ?? '')),
        'phone' => $phone,
        'email' => trim((string)($post['email'] ?? '')),
        'postal_code' => $postalCode,
        'prefecture' => $prefecture,
        'city_area' => $cityArea,
        'street_address' => $streetAddress,
        'building' => $building,
        'address' => $address !== '' ? $address : $legacyAddress,
        'emergency_name' => trim((string)($post['emergency_name'] ?? '')),
        'emergency_relationship' => trim((string)($post['emergency_relationship'] ?? '')),
        'emergency_phone' => $emergencyPhone,
        'guardian_name' => trim((string)($post['guardian_name'] ?? '')),
        'health_checks' => isset($post['health_checks']) && is_array($post['health_checks'])
            ? array_map('strval', $post['health_checks'])
            : [],
        'medical_memo' => trim((string)($post['medical_memo'] ?? '')),
        'terms_agree' => trim((string)($post['terms_agree'] ?? '')),
        'photo_data' => trim((string)($post['photo_data'] ?? '')),
        'photo_token' => trim((string)($post['photo_token'] ?? '')),
        'remarks' => trim((string)($post['remarks'] ?? '')),
    ];
}

function initial_visit_options(array $config, int $monthlyVisits): array
{
    return array_map('intval', $config['initial_visit_options'][$monthlyVisits] ?? [$monthlyVisits]);
}

function normalize_initial_visits(array $config, int $monthlyVisits, string|int|null $postedVisits): int
{
    $options = initial_visit_options($config, $monthlyVisits);
    $visits = (int)$postedVisits;
    if (in_array($visits, $options, true)) {
        return $visits;
    }

    return (int)max($options);
}

function calculate_initial_fee_by_visits(int $monthlyFee, int $monthlyVisits, int $initialVisits): int
{
    if ($monthlyFee <= 0 || $monthlyVisits <= 0 || $initialVisits <= 0) {
        return 0;
    }

    return (int)round($monthlyFee * ($initialVisits / $monthlyVisits));
}

function admission_start_week_proration(string $startDate, int $monthlyVisits): array
{
    if (!is_valid_date_string($startDate)) {
        return [
            'bucket' => '',
            'ratio' => 0.0,
            'visits' => 0,
            'label' => '未設定',
        ];
    }

    $day = (int)(new DateTimeImmutable($startDate))->format('j');
    if ($day <= 7) {
        return ['bucket' => 'day_1_7', 'ratio' => 1.0, 'visits' => $monthlyVisits, 'label' => '1〜7日'];
    }
    if ($day <= 14) {
        return ['bucket' => 'day_8_14', 'ratio' => 0.75, 'visits' => (int)round($monthlyVisits * 0.75), 'label' => '8〜14日'];
    }
    if ($day <= 21) {
        return ['bucket' => 'day_15_21', 'ratio' => 0.5, 'visits' => (int)round($monthlyVisits * 0.5), 'label' => '15〜21日'];
    }

    return ['bucket' => 'day_22_end', 'ratio' => 0.25, 'visits' => (int)round($monthlyVisits * 0.25), 'label' => '22日〜月末'];
}

function calculate_pilates_initial_fee(int $monthlyFee, string $startDate): int
{
    $proration = admission_start_week_proration($startDate, 8);
    return (int)round($monthlyFee * (float)$proration['ratio'], 0, PHP_ROUND_HALF_UP);
}

function calculate_prorated_monthly_fee(int $monthlyFee, string $startDate): int
{
    if ($monthlyFee <= 0 || !is_valid_date_string($startDate)) {
        return 0;
    }

    try {
        $date = new DateTimeImmutable($startDate);
    } catch (Throwable) {
        return 0;
    }

    $daysInMonth = (int)$date->format('t');
    $remainingDays = $daysInMonth - (int)$date->format('j') + 1;
    return (int)round($monthlyFee * ($remainingDays / $daysInMonth), 0, PHP_ROUND_HALF_UP);
}

function normalize_campaign_code(string $code): string
{
    $code = trim($code);
    if (function_exists('mb_convert_kana')) {
        $code = mb_convert_kana($code, 'as', 'UTF-8');
    }
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($code, 'UTF-8');
    }
    return strtoupper($code);
}

function admission_campaigns_file(array $config): string
{
    return (string)($config['admin']['campaigns_file'] ?? (__DIR__ . '/../tmp/campaigns.json'));
}

function default_campaign_settings(array $config): array
{
    $legacy = $config['campaign'] ?? [];
    return [
        [
            'id' => 'default_202607_202608',
            'enabled' => (bool)($legacy['enabled'] ?? false),
            'name' => '7月・8月キャンペーン',
            'code' => '',
            'auto_apply' => true,
            'start_date' => (string)($legacy['start_date'] ?? '2026-07-01'),
            'end_date' => (string)($legacy['end_date'] ?? '2026-08-31'),
            'combinable' => (bool)($legacy['combinable'] ?? false),
            'discount_mode' => 'target_total',
            'discount_amount' => 0,
            'discount_rate' => 0,
            'target_single_total' => (int)($legacy['single_initial_fee'] ?? 0) + (int)($legacy['join_fee'] ?? 0),
            'target_addon_basic_total' => (int)($legacy['addon_initial_fees'][8] ?? 0) + (int)($legacy['join_fee'] ?? 0),
            'target_addon_double_total' => (int)($legacy['addon_initial_fees'][16] ?? 0) + (int)($legacy['join_fee'] ?? 0),
            'discount_rules' => [],
            'note' => 'キャンペーンは初期状態では無効です。利用する場合は確定料金に合わせて設定してください。',
        ],
    ];
}

function campaign_discount_components(): array
{
    return [
        'join_fee' => '入会金',
        'processing_fee' => '手数料',
        'current_month_fee' => '当月会費',
        'main_club_current_month_fee' => '本館会費（当月）',
        'pilates_current_month_fee' => 'Find Pilates種別（当月）',
        'next_month_fee' => '翌月会費',
        'main_club_next_month_fee' => '本館会費（翌月）',
        'pilates_next_month_fee' => 'Find Pilates種別（翌月）',
        'initial_total' => '初回合計',
    ];
}

function campaign_plan_scopes(): array
{
    return [
        'all' => 'すべて',
        'single_basic' => '単体 ベーシック',
        'single_double' => '単体 ダブル',
        'addon_basic' => '本館併用 ベーシック',
        'addon_double' => '本館併用 ダブル',
    ];
}

function normalize_campaign_rule(array $rule): array
{
    $component = (string)($rule['component'] ?? 'current_month_fee');
    if (!isset(campaign_discount_components()[$component])) {
        $component = 'current_month_fee';
    }

    $scope = (string)($rule['scope'] ?? 'all');
    if (!isset(campaign_plan_scopes()[$scope])) {
        $scope = 'all';
    }

    $type = (string)($rule['discount_type'] ?? 'amount');
    if (!in_array($type, ['amount', 'free', 'target_amount', 'percent'], true)) {
        $type = 'amount';
    }
    $amount = $type === 'percent'
        ? min(100, max(0, (float)($rule['amount'] ?? 0)))
        : max(0, (int)($rule['amount'] ?? 0));

    return [
        'enabled' => !empty($rule['enabled']),
        'component' => $component,
        'scope' => $scope,
        'discount_type' => $type,
        'amount' => $amount,
    ];
}

function normalize_campaign_setting(array $campaign): array
{
    $id = (string)($campaign['id'] ?? '');
    if ($id === '') {
        $id = 'cp_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
    }

    $mode = (string)($campaign['discount_mode'] ?? 'amount');
    if (!in_array($mode, ['amount', 'percent', 'target_total', 'rules'], true)) {
        $mode = 'amount';
    }

    $rules = [];
    if (isset($campaign['discount_rules']) && is_array($campaign['discount_rules'])) {
        foreach ($campaign['discount_rules'] as $rule) {
            if (is_array($rule)) {
                $rules[] = normalize_campaign_rule($rule);
            }
        }
    }

    return [
        'id' => $id,
        'enabled' => !empty($campaign['enabled']),
        'name' => trim((string)($campaign['name'] ?? '')),
        'code' => normalize_campaign_code((string)($campaign['code'] ?? '')),
        'auto_apply' => !empty($campaign['auto_apply']),
        'start_date' => trim((string)($campaign['start_date'] ?? '')),
        'end_date' => trim((string)($campaign['end_date'] ?? '')),
        'combinable' => !empty($campaign['combinable']),
        'discount_mode' => $mode,
        'discount_amount' => max(0, (int)($campaign['discount_amount'] ?? 0)),
        'discount_rate' => min(100, max(0, (float)($campaign['discount_rate'] ?? 0))),
        'target_single_total' => max(0, (int)($campaign['target_single_total'] ?? 0)),
        'target_addon_basic_total' => max(0, (int)($campaign['target_addon_basic_total'] ?? 0)),
        'target_addon_double_total' => max(0, (int)($campaign['target_addon_double_total'] ?? 0)),
        'discount_rules' => $rules,
        'note' => trim((string)($campaign['note'] ?? '')),
    ];
}

function load_campaign_settings(array $config): array
{
    $file = admission_campaigns_file($config);
    if ($file !== '' && is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $items = is_array($decoded['campaigns'] ?? null) ? $decoded['campaigns'] : $decoded;
            return array_map(
                static fn($campaign) => normalize_campaign_setting(is_array($campaign) ? $campaign : []),
                $items
            );
        }
    }

    return default_campaign_settings($config);
}

function save_campaign_settings(array $config, array $campaigns): bool
{
    $file = admission_campaigns_file($config);
    if ($file === '') {
        return false;
    }

    ensure_dir(dirname($file));
    $normalized = array_values(array_map(
        static fn($campaign) => normalize_campaign_setting(is_array($campaign) ? $campaign : []),
        $campaigns
    ));
    $json = json_encode(['campaigns' => $normalized], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents($file, $json, LOCK_EX) !== false;
}

function campaign_is_active(array $campaign, string $startDate, string $enteredCode): bool
{
    if (empty($campaign['enabled']) || $startDate === '' || !is_valid_date_string($startDate)) {
        return false;
    }

    $from = (string)($campaign['start_date'] ?? '');
    $to = (string)($campaign['end_date'] ?? '');
    if ($from !== '' && is_valid_date_string($from) && $startDate < $from) {
        return false;
    }
    if ($to !== '' && is_valid_date_string($to) && $startDate > $to) {
        return false;
    }

    $code = normalize_campaign_code((string)($campaign['code'] ?? ''));
    if (!empty($campaign['auto_apply']) && $code === '') {
        return true;
    }

    return $code !== '' && $code === normalize_campaign_code($enteredCode);
}

function active_campaign_settings(array $config, array $data): array
{
    $startDate = (string)($data['start_date'] ?? '');
    $enteredCode = (string)($data['campaign_code'] ?? '');
    return array_values(array_filter(
        load_campaign_settings($config),
        static fn(array $campaign): bool => campaign_is_active($campaign, $startDate, $enteredCode)
    ));
}

function campaign_plan_key(string $useType, int $monthlyVisits): string
{
    if ($useType === 'add') {
        return $monthlyVisits === 16 ? 'addon_double' : 'addon_basic';
    }

    return $monthlyVisits === 16 ? 'single_double' : 'single_basic';
}

function campaign_rule_matches_plan(array $rule, string $planKey): bool
{
    $scope = (string)($rule['scope'] ?? 'all');
    return $scope === 'all' || $scope === $planKey;
}

function campaign_component_label(string $component): string
{
    return campaign_discount_components()[$component] ?? $component;
}

function campaign_component_base_amount(array $components, string $component): int
{
    if ($component === 'initial_total') {
        return array_sum($components);
    }

    return (int)($components[$component] ?? 0);
}

function campaign_rule_discount_amount(array $rule, int $baseAmount, int $remainingAmount): int
{
    if ($baseAmount <= 0 || $remainingAmount <= 0) {
        return 0;
    }

    $type = (string)($rule['discount_type'] ?? 'amount');
    if ($type === 'free') {
        return $remainingAmount;
    }
    if ($type === 'target_amount') {
        return min($remainingAmount, max(0, $baseAmount - (int)($rule['amount'] ?? 0)));
    }
    if ($type === 'percent') {
        $rate = min(100, max(0, (float)($rule['amount'] ?? 0)));
        return min($remainingAmount, (int)round($remainingAmount * ($rate / 100)));
    }

    return min($remainingAmount, max(0, (int)($rule['amount'] ?? 0)));
}

function campaign_uses_component(array $campaigns, string $component, string $planKey): bool
{
    foreach ($campaigns as $campaign) {
        if (($campaign['discount_mode'] ?? '') !== 'rules') {
            continue;
        }
        foreach (($campaign['discount_rules'] ?? []) as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            if (($rule['component'] ?? '') === $component && campaign_rule_matches_plan($rule, $planKey)) {
                return true;
            }
        }
    }

    return false;
}

function campaign_target_total(array $campaign, string $useType, int $monthlyVisits): ?int
{
    if ($useType === 'add') {
        if ($monthlyVisits === 8) {
            return (int)($campaign['target_addon_basic_total'] ?? 0);
        }
        if ($monthlyVisits === 16) {
            return (int)($campaign['target_addon_double_total'] ?? 0);
        }
        return null;
    }

    return (int)($campaign['target_single_total'] ?? 0);
}

function campaign_discount_amount(array $campaign, string $useType, int $monthlyVisits, int $regularInitialTotal): int
{
    $mode = (string)($campaign['discount_mode'] ?? 'amount');
    if ($mode === 'target_total') {
        $target = campaign_target_total($campaign, $useType, $monthlyVisits);
        return $target === null ? 0 : max(0, $regularInitialTotal - $target);
    }
    if ($mode === 'percent') {
        $rate = min(100, max(0, (float)($campaign['discount_rate'] ?? 0)));
        return min($regularInitialTotal, (int)round($regularInitialTotal * ($rate / 100)));
    }

    return min($regularInitialTotal, max(0, (int)($campaign['discount_amount'] ?? 0)));
}

function applied_campaign_discounts(array $campaigns, string $useType, int $monthlyVisits, int $regularInitialTotal, array $components): array
{
    $candidates = [];
    $planKey = campaign_plan_key($useType, $monthlyVisits);

    foreach ($campaigns as $campaign) {
        $details = [];
        $amount = 0;
        if (($campaign['discount_mode'] ?? '') === 'rules') {
            $remainingByComponent = $components;
            $remainingByComponent['initial_total'] = $regularInitialTotal;
            foreach (($campaign['discount_rules'] ?? []) as $rule) {
                if (empty($rule['enabled']) || !campaign_rule_matches_plan($rule, $planKey)) {
                    continue;
                }

                $component = (string)($rule['component'] ?? 'current_month_fee');
                $baseAmount = campaign_component_base_amount($components, $component);
                $remaining = (int)($remainingByComponent[$component] ?? $baseAmount);
                $ruleDiscount = campaign_rule_discount_amount($rule, $baseAmount, $remaining);
                if ($ruleDiscount <= 0) {
                    continue;
                }

                $amount += $ruleDiscount;
                $remainingByComponent[$component] = max(0, $remaining - $ruleDiscount);
                if ($component !== 'initial_total') {
                    $remainingByComponent['initial_total'] = max(0, (int)($remainingByComponent['initial_total'] ?? $regularInitialTotal) - $ruleDiscount);
                }
                $details[] = [
                    'component' => $component,
                    'component_label' => campaign_component_label($component),
                    'amount' => $ruleDiscount,
                ];
            }
        } else {
            $amount = campaign_discount_amount($campaign, $useType, $monthlyVisits, $regularInitialTotal);
        }
        if ($amount <= 0) {
            continue;
        }

        $campaign['amount'] = $amount;
        $campaign['details'] = $details;
        $candidates[] = $campaign;
    }

    if (empty($candidates)) {
        return [];
    }

    $allCombinable = array_reduce(
        $candidates,
        static fn(bool $carry, array $campaign): bool => $carry && !empty($campaign['combinable']),
        true
    );

    if (!$allCombinable) {
        usort($candidates, static fn(array $a, array $b): int => (int)$b['amount'] <=> (int)$a['amount']);
        $best = $candidates[0];
        $best['amount'] = min($regularInitialTotal, (int)$best['amount']);
        return [$best];
    }

    $remaining = $regularInitialTotal;
    $applied = [];
    foreach ($candidates as $campaign) {
        $amount = min($remaining, (int)$campaign['amount']);
        if ($amount <= 0) {
            continue;
        }
        $campaign['amount'] = $amount;
        $applied[] = $campaign;
        $remaining -= $amount;
    }

    return $applied;
}

function calculate_fees(array $config, array $data): array
{
    $useType = ($data['use_type'] ?? 'new') === 'add' ? 'add' : 'new';
    $joinFee = (int)($config['fees']['join_fee'] ?? 0);
    $processingFee = (int)($config['fees']['processing_fee'] ?? 0);

    $courseLabel = '';
    $courseDescription = '';
    $monthlyVisits = 0;
    $initialVisits = 0;
    $baseMonthlyFee = 0;
    $mainClubInitialFee = 0;
    $addonFee = 0;
    $addonInitialFee = 0;
    $pilatesMonthlyFee = 0;
    $pilatesInitialFee = 0;
    $monthlyFee = 0;
    $regularInitialTotal = $joinFee;
    $mainMembershipLabel = '';
    $mainMembershipDescription = '';
    $addonLabel = '';
    $addonDescription = '';
    $mainMemberStatus = (string)($data['main_member_status'] ?? 'existing');
    if ($useType === 'add' && $mainMemberStatus === 'existing') {
        $joinFee = 0;
    }
    $startDate = (string)($data['start_date'] ?? '');

    if ($useType === 'add') {
        $main = $config['main_club_memberships'][$data['main_membership'] ?? ''] ?? null;
        $addon = $config['pilates_addons'][$data['addon'] ?? ''] ?? null;

        if ($main) {
            $mainMembershipLabel = (string)$main['label'];
            $mainMembershipDescription = (string)$main['description'];
            $baseMonthlyFee = (int)$main['monthly_fee'];
            if ($mainMemberStatus === 'simultaneous') {
                $mainClubInitialFee = calculate_prorated_monthly_fee($baseMonthlyFee, $startDate);
            }
        }

        if ($addon) {
            $addonLabel = (string)$addon['label'];
            $addonDescription = (string)$addon['description'];
            $addonFee = (int)$addon['add_fee'];
            $monthlyVisits = (int)$addon['monthly_visits'];
            $proration = admission_start_week_proration($startDate, $monthlyVisits);
            $initialVisits = (int)$proration['visits'];
            $addonInitialFee = calculate_pilates_initial_fee($addonFee, $startDate);
        }

        $pilatesMonthlyFee = $addonFee;
        $monthlyFee = $baseMonthlyFee + $addonFee;
        $courseLabel = trim($mainMembershipLabel . ' ＋ ' . $addonLabel);
        $courseDescription = trim($mainMembershipDescription . "\n" . $addonDescription);
    } else {
        $course = $config['pilates_courses'][$data['course'] ?? ''] ?? null;

        if ($course) {
            $courseLabel = (string)$course['label'];
            $courseDescription = (string)$course['description'];
            $monthlyVisits = (int)$course['monthly_visits'];
            $pilatesMonthlyFee = (int)$course['monthly_fee'];
            $proration = admission_start_week_proration($startDate, $monthlyVisits);
            $initialVisits = (int)$proration['visits'];
            $pilatesInitialFee = calculate_pilates_initial_fee($pilatesMonthlyFee, $startDate);
            $monthlyFee = $pilatesMonthlyFee;
        }
    }

    $activeCampaigns = active_campaign_settings($config, $data);
    $planKey = campaign_plan_key($useType, $monthlyVisits);
    $pilatesCurrentMonthFee = $pilatesInitialFee + $addonInitialFee;
    $currentMonthFee = $mainClubInitialFee + $pilatesCurrentMonthFee;
    $nextMonthFee = $pilatesMonthlyFee;
    $usesSplitCurrentMonth = $useType === 'add' && (
        campaign_uses_component($activeCampaigns, 'main_club_current_month_fee', $planKey)
        || campaign_uses_component($activeCampaigns, 'pilates_current_month_fee', $planKey)
    );
    $usesSplitNextMonth = $useType === 'add' && (
        campaign_uses_component($activeCampaigns, 'main_club_next_month_fee', $planKey)
        || campaign_uses_component($activeCampaigns, 'pilates_next_month_fee', $planKey)
    );
    $components = [
        'join_fee' => $joinFee,
        'processing_fee' => $processingFee,
    ];
    if ($usesSplitCurrentMonth) {
        if ($mainClubInitialFee > 0) {
            $components['main_club_current_month_fee'] = $mainClubInitialFee;
        }
        $components['pilates_current_month_fee'] = $pilatesCurrentMonthFee;
    } else {
        $components['current_month_fee'] = $currentMonthFee;
    }
    if ($usesSplitNextMonth) {
        if ($baseMonthlyFee > 0) {
            $components['main_club_next_month_fee'] = $baseMonthlyFee;
        }
        $components['pilates_next_month_fee'] = $nextMonthFee;
    } elseif (campaign_uses_component($activeCampaigns, 'next_month_fee', $planKey)) {
        $components['next_month_fee'] = $nextMonthFee;
    }

    $regularInitialTotal = array_sum($components);
    $campaignDiscounts = applied_campaign_discounts($activeCampaigns, $useType, $monthlyVisits, $regularInitialTotal, $components);
    $campaignDiscount = array_reduce(
        $campaignDiscounts,
        static fn(int $sum, array $campaign): int => $sum + (int)($campaign['amount'] ?? 0),
        0
    );
    $initialTotal = max(0, $regularInitialTotal - $campaignDiscount);

    return [
        'use_type' => $useType,
        'course_label' => $courseLabel,
        'course_description' => $courseDescription,
        'main_member_status' => $mainMemberStatus,
        'main_membership_label' => $mainMembershipLabel,
        'main_membership_description' => $mainMembershipDescription,
        'addon_label' => $addonLabel,
        'addon_description' => $addonDescription,
        'monthly_visits' => $monthlyVisits,
        'initial_visits' => $initialVisits,
        'initial_rate' => $monthlyVisits > 0 ? $initialVisits / $monthlyVisits : 0,
        'proration' => admission_start_week_proration($startDate, $monthlyVisits),
        'base_monthly_fee' => $baseMonthlyFee,
        'main_club_initial_fee' => $mainClubInitialFee,
        'addon_fee' => $addonFee,
        'addon_initial_fee' => $addonInitialFee,
        'pilates_monthly_fee' => $pilatesMonthlyFee,
        'pilates_initial_fee' => $pilatesInitialFee,
        'monthly_fee' => $monthlyFee,
        'join_fee' => $joinFee,
        'processing_fee' => $processingFee,
        'current_month_fee' => $currentMonthFee,
        'main_club_current_month_fee' => (int)($components['main_club_current_month_fee'] ?? 0),
        'pilates_current_month_fee' => $pilatesCurrentMonthFee,
        'next_month_fee' => isset($components['next_month_fee']) ? $nextMonthFee : 0,
        'main_club_next_month_fee' => (int)($components['main_club_next_month_fee'] ?? 0),
        'pilates_next_month_fee' => (int)($components['pilates_next_month_fee'] ?? 0),
        'fee_components' => $components,
        'regular_initial_total' => $regularInitialTotal,
        'campaign_applied' => $campaignDiscount > 0,
        'campaign_discount' => $campaignDiscount,
        'campaign_discounts' => array_map(static function (array $campaign): array {
            return [
                'name' => (string)($campaign['name'] ?? ''),
                'code' => (string)($campaign['code'] ?? ''),
                'auto_apply' => !empty($campaign['auto_apply']),
                'amount' => (int)($campaign['amount'] ?? 0),
                'details' => is_array($campaign['details'] ?? null) ? $campaign['details'] : [],
            ];
        }, $campaignDiscounts),
        'initial_total' => $initialTotal,
    ];
}

function is_valid_date_string(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y);
}

function is_past_date(string $date): bool
{
    try {
        return new DateTimeImmutable($date) < new DateTimeImmutable('today');
    } catch (Throwable) {
        return false;
    }
}

function selectable_min_date(array $config): string
{
    $minDate = (string)($config['date_selection']['min_date'] ?? date('Y-m-d'));
    return is_valid_date_string($minDate) ? $minDate : date('Y-m-d');
}

function is_before_selectable_min_date(array $config, string $date): bool
{
    try {
        return new DateTimeImmutable($date) < new DateTimeImmutable(selectable_min_date($config));
    } catch (Throwable) {
        return false;
    }
}

function is_future_date(string $date): bool
{
    try {
        return new DateTimeImmutable($date) > new DateTimeImmutable('today');
    } catch (Throwable) {
        return false;
    }
}

function is_closed_date(array $config, string $date): bool
{
    if (in_array($date, $config['closed_dates'] ?? [], true)) {
        return true;
    }

    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return false;
    }

    $weekday = (int)$dt->format('w');
    return in_array($weekday, $config['closed_day_rules'] ?? [], true);
}

function valid_procedure_time_for_date(array $config, string $date, string $time): bool
{
    if ($time === '') {
        return true;
    }

    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return false;
    }

    $slots = (int)$dt->format('w') === 0
        ? ($config['procedure_time_slots_sunday'] ?? [])
        : ($config['procedure_time_slots_weekday'] ?? []);

    return isset($slots[$time]);
}

function validate_form(array $config, array $data, bool $isConfirm = false): array
{
    $errors = [];

    if (!verify_csrf_token($data['csrf_token'] ?? null)) {
        $errors['csrf'] = '不正な送信です。ページを再読み込みしてもう一度お試しください。';
    }

    if (!in_array($data['use_type'], ['new', 'add'], true)) {
        $errors['use_type'] = '利用形態を選択してください。';
    }

    if ($data['use_type'] === 'new' && !in_array((string)($data['course'] ?? ''), ['basic', 'double'], true)) {
        $errors['course'] = 'プランを選択してください。';
    }

    if ($data['use_type'] === 'add') {
        if (!in_array($data['main_member_status'], ['existing', 'simultaneous'], true)) {
            $errors['main_member_status'] = '本館の会員状況を選択してください。';
        }
        if (!isset($config['main_club_memberships'][$data['main_membership']])) {
            $errors['main_membership'] = '本館会員種別を選択してください。';
        }
        if (!in_array((string)($data['addon'] ?? ''), ['basic', 'double'], true)) {
            $errors['addon'] = 'Find Pilates種別を選択してください。';
        }
        if (($data['main_membership'] ?? '') === 'weekend') {
            $errors['main_membership'] = 'ウィークエンド会員は現在Web入会の新規選択対象外です。別の本館会員種別を選択してください。';
        }
    }

    foreach (['start_date' => '利用開始希望日'] as $key => $label) {
        if ($data[$key] === '') {
            $errors[$key] = $label . 'を選択してください。';
        } elseif (!is_valid_date_string($data[$key])) {
            $errors[$key] = $label . 'の日付形式を確認してください。';
        } elseif (is_before_selectable_min_date($config, $data[$key])) {
            $errors[$key] = $label . 'は' . date_label(selectable_min_date($config)) . '以降を選択してください。';
        }
    }

    for ($i = 1; $i <= 3; $i++) {
        $dateKey = 'procedure_date_' . $i;
        $timeKey = 'procedure_time_' . $i;
        $date = (string)($data[$dateKey] ?? '');
        $time = (string)($data[$timeKey] ?? '');
        $required = $i === 1 || (bool)($config['require_second_third_preferences'] ?? false);

        if ($required && $date === '') {
            $errors[$dateKey] = '第' . $i . '希望日を選択してください。';
            continue;
        }
        if ($date === '') {
            continue;
        }
        if (!is_valid_date_string($date)) {
            $errors[$dateKey] = '第' . $i . '希望日の日付形式を確認してください。';
        } elseif (is_before_selectable_min_date($config, $date)) {
            $errors[$dateKey] = '第' . $i . '希望日は' . date_label(selectable_min_date($config)) . '以降を選択してください。';
        } elseif (is_closed_date($config, $date)) {
            $errors[$dateKey] = '第' . $i . '希望日は受付不可日です。';
        } elseif (!valid_procedure_time_for_date($config, $date, $time)) {
            $errors[$timeKey] = '第' . $i . '希望の時間帯が営業時間外です。';
        }
    }

    $seenPrefs = [];
    for ($i = 1; $i <= 3; $i++) {
        $date = (string)($data['procedure_date_' . $i] ?? '');
        $time = (string)($data['procedure_time_' . $i] ?? '');
        if ($date === '' || $time === '') {
            continue;
        }
        $key = $date . '|' . $time;
        if (isset($seenPrefs[$key])) {
            $errors['procedure_duplicate'] = '来店希望日の日時が重複しています。別の日時を選択してください。';
            break;
        }
        $seenPrefs[$key] = true;
    }

    if (($data['main_membership'] ?? '') === 'night_holiday_u34'
        && is_valid_date_string((string)($data['birth'] ?? ''))
        && is_valid_date_string((string)($data['start_date'] ?? ''))
    ) {
        $birthDate = new DateTimeImmutable((string)$data['birth']);
        $startDate = new DateTimeImmutable((string)$data['start_date']);
        if ((int)$birthDate->diff($startDate)->y >= 35) {
            $errors['main_membership'] = 'ナイト＆ホリデー会員（34才以下）は、利用開始日時点で34歳以下の方が対象です。';
        }
    }

    foreach ([
        'surname' => '姓',
        'given_name' => '名',
        'surname_kana' => 'セイ',
        'given_name_kana' => 'メイ',
    ] as $key => $label) {
        if (($data[$key] ?? '') === '') {
            $errors[$key] = $label . 'を入力してください。';
        } elseif (admission_text_length((string)$data[$key]) > 28) {
            $errors[$key] = $label . 'は28文字以内で入力してください。';
        }
    }

    $kanaValue = trim((string)($data['surname_kana'] ?? '') . (string)($data['given_name_kana'] ?? ''));
    if ($kanaValue !== '' && !preg_match('/^[\x{30A0}-\x{30FF}\x{3000}\s]+$/u', $kanaValue)) {
        $errors['kana'] = 'セイ・メイは全角カタカナで入力してください。';
    }

    if ($data['birth'] === '') {
        $errors['birth'] = '生年月日を選択してください。';
    } elseif (!is_valid_date_string($data['birth'])) {
        $errors['birth'] = '生年月日の日付形式を確認してください。';
    } elseif (is_future_date($data['birth'])) {
        $errors['birth'] = '生年月日に未来の日付は指定できません。';
    }

    $age = calculate_age($data['birth']);
    if ($age !== null && $age < 15) {
        $errors['birth'] = '15歳未満および中学生以下の方は入会できません。';
    }
    if ($age !== null && $age < 18 && $data['guardian_name'] === '') {
        $errors['guardian_name'] = '未成年の方は保護者氏名を入力してください。';
    }
    if ($age !== null && $age >= 15 && $age <= 16 && $data['school_confirmation'] !== '1') {
        $errors['school_confirmation'] = '15歳前後の方は、中学生以下ではないことの確認にチェックしてください。';
    }

    if (!in_array((string)($data['gender'] ?? ''), ['男性', '女性', 'その他'], true)) {
        $errors['gender'] = '性別を選択してください。';
    }
    if (!in_array((string)($data['phone_type'] ?? ''), ['mobile', 'home'], true)) {
        $errors['phone_type'] = '電話番号種別を選択してください。';
    }
    if ($data['phone'] === '') {
        $errors['phone'] = '電話番号を入力してください。';
    } elseif (strlen($data['phone']) < 10 || strlen($data['phone']) > 11) {
        $errors['phone'] = '電話番号の形式を確認してください。';
    }
    if ($data['email'] === '') {
        $errors['email'] = 'メールアドレスを入力してください。';
    } elseif (!is_valid_email($data['email'])) {
        $errors['email'] = 'メールアドレスの形式を確認してください。';
    }
    if ($data['postal_code'] !== '' && strlen($data['postal_code']) !== 7) {
        $errors['postal_code'] = '郵便番号は7桁で入力してください。';
    }
    if ($data['prefecture'] === '') {
        $errors['prefecture'] = '都道府県を入力してください。';
    }
    if ($data['city_area'] === '') {
        $errors['city_area'] = '市区町村・町域を入力してください。';
    } elseif (admission_text_length((string)$data['city_area']) > 30) {
        $errors['city_area'] = '住所1（市区町村・町域）は30文字以内で入力してください。';
    }
    if ($data['street_address'] === '') {
        $errors['street_address'] = '番地を入力してください。';
    } elseif (admission_text_length((string)$data['street_address']) > 30) {
        $errors['street_address'] = '住所2（番地）は30文字以内で入力してください。';
    }
    if (admission_text_length((string)($data['building'] ?? '')) > 30) {
        $errors['building'] = '住所3（建物名・部屋番号）は30文字以内で入力してください。';
    }
    if ($data['emergency_name'] === '') {
        $errors['emergency_name'] = '緊急連絡先の氏名を入力してください。';
    }
    if ($data['emergency_relationship'] === '') {
        $errors['emergency_relationship'] = '緊急連絡先の続柄を入力してください。';
    }
    if ($data['emergency_phone'] === '') {
        $errors['emergency_phone'] = '緊急連絡先の電話番号を入力してください。';
    } elseif (strlen($data['emergency_phone']) < 10 || strlen($data['emergency_phone']) > 11) {
        $errors['emergency_phone'] = '緊急連絡先の電話番号の形式を確認してください。';
    }

    foreach ($config['health_checks'] as $key => $item) {
        if (!empty($item['required']) && !in_array($key, $data['health_checks'], true)) {
            $errors['health_checks'] = '健康確認項目にすべて同意してください。';
            break;
        }
    }

    if ($data['terms_agree'] !== '1') {
        $errors['terms_agree'] = 'クラブ規約を確認し、同意してください。';
    }

    if (!$isConfirm && ($config['photo']['required'] ?? true)) {
        if ($data['photo_token'] === '' && $data['photo_data'] === '') {
            $errors['photo'] = '顔写真を撮影またはアップロードしてください。';
        }
    }

    return $errors;
}

function ensure_dir(string $dir): void
{
    if ($dir !== '' && !is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
}

function handle_photo(array $config, array $data, array $files): array
{
    ensure_dir((string)($config['photo']['tmp_dir'] ?? ''));

    if (!empty($data['photo_data'])) {
        return save_base64_photo($config, $data['photo_data']);
    }

    if (isset($files['photo_file']) && is_uploaded_file($files['photo_file']['tmp_name'] ?? '')) {
        return save_uploaded_photo($config, $files['photo_file']);
    }

    return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真がありません。'];
}

function validate_image_binary(string $binary): bool
{
    return @getimagesizefromstring($binary) !== false;
}

function save_base64_photo(array $config, string $base64): array
{
    if (!preg_match('/^data:(image\/jpeg|image\/png|image\/webp);base64,(.+)$/', $base64, $matches)) {
        return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真データの形式が正しくありません。'];
    }

    $mime = $matches[1];
    $binary = base64_decode($matches[2], true);
    if ($binary === false || !validate_image_binary($binary)) {
        return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真データを画像として読み取れませんでした。'];
    }

    $maxBytes = (int)($config['photo']['upload_max_bytes'] ?? 0);
    if ($maxBytes > 0 && strlen($binary) > $maxBytes) {
        return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真の容量が大きすぎます。'];
    }

    $ext = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    $filename = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $path = rtrim((string)$config['photo']['tmp_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($path, $binary);
    chmod($path, 0600);

    return ['ok' => true, 'path' => $path, 'filename' => $filename, 'mime' => $mime, 'error' => ''];
}

function save_uploaded_photo(array $config, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真のアップロードに失敗しました。'];
    }

    $maxBytes = (int)($config['photo']['upload_max_bytes'] ?? 0);
    if ($maxBytes > 0 && (int)$file['size'] > $maxBytes) {
        return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真の容量が大きすぎます。'];
    }

    $mime = mime_content_type($file['tmp_name']) ?: '';
    $allowed = $config['photo']['allowed_mime'] ?? [];
    $binary = (string)file_get_contents($file['tmp_name']);

    if (!in_array($mime, $allowed, true) || !validate_image_binary($binary)) {
        return ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => '顔写真はJPEG、PNG、WebP形式でアップロードしてください。'];
    }

    $ext = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    $filename = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $path = rtrim((string)$config['photo']['tmp_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;
    move_uploaded_file($file['tmp_name'], $path);
    chmod($path, 0600);

    return ['ok' => true, 'path' => $path, 'filename' => $filename, 'mime' => $mime, 'error' => ''];
}

function time_slot_label(array $config, string $key): string
{
    return (string)(($config['procedure_time_slots'][$key] ?? $config['procedure_time_slots_weekday'][$key] ?? $key));
}

function health_check_labels(array $config, array $checkedKeys): array
{
    $labels = [];
    foreach ($checkedKeys as $key) {
        if (isset($config['health_checks'][$key])) {
            $labels[] = (string)$config['health_checks'][$key]['label'];
        }
    }
    return $labels;
}

function use_type_label(array $data): string
{
    return ($data['use_type'] ?? '') === 'add' ? '本館併用' : 'Find Pilates単体';
}

function main_member_status_label(array $data): string
{
    return ($data['main_member_status'] ?? '') === 'simultaneous'
        ? 'いいえ'
        : 'はい';
}

function fee_summary_lines(array $data, array $fees): array
{
    $lines = [
        '利用形態：' . use_type_label($data),
        '選択プラン：' . ($fees['course_label'] ?? ''),
        '月間利用可能回数：' . (string)($fees['monthly_visits'] ?? 0) . '回',
        '利用開始希望日：' . date_label($data['start_date'] ?? ''),
        '初月計算区分：' . (string)($fees['proration']['label'] ?? ''),
        '初月の利用可能回数：' . (string)($fees['initial_visits'] ?? 0) . '回',
        '通常月会費：' . yen((int)($fees['monthly_fee'] ?? 0)),
        '初月会費合計：' . yen((int)($fees['current_month_fee'] ?? 0)),
    ];

    if ((int)($fees['join_fee'] ?? 0) > 0) {
        $lines[] = '入会費：' . yen((int)$fees['join_fee']);
    }

    if (($data['use_type'] ?? '') === 'add') {
        $lines[] = '本館初月会費（日割）：' . yen((int)($fees['main_club_initial_fee'] ?? 0));
        $lines[] = 'Find Pilates初月会費：' . yen((int)($fees['pilates_current_month_fee'] ?? 0));
    }

    if ((int)($fees['processing_fee'] ?? 0) > 0) {
        $lines[] = '手数料：' . yen((int)$fees['processing_fee']);
    }

    if ((int)($fees['next_month_fee'] ?? 0) > 0) {
        $lines[] = '翌月会費：' . yen((int)$fees['next_month_fee']);
    }
    if ((int)($fees['main_club_next_month_fee'] ?? 0) > 0) {
        $lines[] = '翌月本館会費：' . yen((int)$fees['main_club_next_month_fee']);
    }
    if ((int)($fees['pilates_next_month_fee'] ?? 0) > 0) {
        $lines[] = '翌月Find Pilates種別：' . yen((int)$fees['pilates_next_month_fee']);
    }

    if (($data['use_type'] ?? '') === 'add') {
        $lines[] = '本館通常月会費：' . yen((int)($fees['base_monthly_fee'] ?? 0));
        $lines[] = 'Find Pilates種別：' . yen((int)($fees['addon_fee'] ?? 0));
    }

    if ((int)($fees['campaign_discount'] ?? 0) > 0) {
        $lines[] = 'キャンペーン値引：-' . yen((int)$fees['campaign_discount']);
        foreach (($fees['campaign_discounts'] ?? []) as $campaign) {
            $name = (string)($campaign['name'] ?? '');
            $code = (string)($campaign['code'] ?? '');
            $label = $name !== '' ? $name : ($code !== '' ? $code : 'キャンペーン');
            $lines[] = '　適用：' . $label . '（-' . yen((int)($campaign['amount'] ?? 0)) . '）';
        }
    }

    $lines[] = '初回概算合計：' . yen((int)($fees['initial_total'] ?? 0));
    return $lines;
}

function build_admin_mail_body(array $config, array $data, array $fees, array $photo = []): string
{
    $age = calculate_age($data['birth']);
    $healthLabels = health_check_labels($config, $data['health_checks']);
    $lines = [];

    $lines[] = 'Find Pilates Web入会受付がありました。';
    $lines[] = 'フロントで内容確認のうえ、店頭手続き希望日の調整をお願いします。';
    $lines[] = '';
    $lines[] = '【受付情報】';
    $lines[] = '受付日時：' . date('Y/m/d H:i');
    $lines[] = '利用形態：' . use_type_label($data);
    if (($data['use_type'] ?? '') === 'add') {
        $lines[] = '現在、本館をご利用中ですか？：' . main_member_status_label($data);
        $lines[] = '本館会員番号：' . ($data['main_member_number'] ?: '未入力');
    }
    $lines[] = '';
    $lines[] = '【料金】';
    foreach (fee_summary_lines($data, $fees) as $line) {
        $lines[] = $line;
    }
    $lines[] = 'キャンペーンコード：' . ($data['campaign_code'] ?: 'なし');
    $lines[] = '';
    $lines[] = '【来店希望日時】';
    for ($i = 1; $i <= 3; $i++) {
        $lines[] = '第' . $i . '希望：' . (date_label($data['procedure_date_' . $i] ?? '') ?: '未入力') . ' ' . time_slot_label($config, (string)($data['procedure_time_' . $i] ?? ''));
    }
    $lines[] = '';
    $lines[] = '【お客様情報】';
    $lines[] = '氏名：' . $data['name'];
    $lines[] = 'フリガナ：' . $data['kana'];
    $lines[] = '生年月日：' . date_label($data['birth']) . ($age === null ? '' : '（' . $age . '歳）');
    $lines[] = '性別：' . ($data['gender'] ?: '未入力');
    $lines[] = '電話番号種別：' . (($data['phone_type'] ?? '') === 'mobile' ? '携帯TEL' : (($data['phone_type'] ?? '') === 'home' ? '自宅TEL' : '未入力'));
    $lines[] = '電話番号：' . $data['phone'];
    $lines[] = 'メール：' . $data['email'];
    $lines[] = '郵便番号：' . ($data['postal_code'] ?: '未入力');
    $lines[] = '住所：' . $data['address'];
    $lines[] = '緊急連絡先：' . $data['emergency_name'] . '（' . $data['emergency_relationship'] . '） ' . $data['emergency_phone'];
    $lines[] = '保護者氏名：' . ($data['guardian_name'] ?: '該当なし');
    $lines[] = '';
    $lines[] = '【健康確認】';
    foreach ($healthLabels as $label) {
        $lines[] = '・' . $label;
    }
    $lines[] = '補足：' . ($data['medical_memo'] ?: 'なし');
    $lines[] = '';
    $lines[] = '【顔写真】';
    $lines[] = !empty($photo['filename']) ? '添付あり：' . $photo['filename'] : '添付なし';
    $lines[] = '';
    $lines[] = '【連絡事項】';
    $lines[] = $data['remarks'] ?: 'なし';

    return implode("\n", $lines);
}

function build_user_mail_body(array $config, array $data, array $fees): string
{
    $site = $config['site'];
    $lines = [];

    $lines[] = $data['name'] . ' 様';
    $lines[] = '';
    $lines[] = 'このたびはFind Pilatesの入会受付フォームをご利用いただき、ありがとうございます。';
    $lines[] = '以下の内容で受付いたしました。';
    $lines[] = '';
    $lines[] = '【お申し込み内容】';
    foreach (fee_summary_lines($data, $fees) as $line) {
        $lines[] = $line;
    }
    $lines[] = '';
    $lines[] = '【来店希望日時】';
    for ($i = 1; $i <= 3; $i++) {
        $lines[] = '第' . $i . '希望：' . (date_label($data['procedure_date_' . $i] ?? '') ?: '未入力') . ' ' . time_slot_label($config, (string)($data['procedure_time_' . $i] ?? ''));
    }
    $lines[] = '';
    $lines[] = $config['procedure_info']['contact_notice'];
    $lines[] = '';
    $lines[] = '【当日お持ちいただくもの】';
    foreach ($config['procedure_info']['things_to_bring'] as $item) {
        $lines[] = '・' . $item;
    }
    $lines[] = '';
    $lines[] = 'ご不明な点がございましたら、お気軽にお問い合わせください。';
    $lines[] = '';
    $lines[] = $site['name'];
    $lines[] = $site['club_name'];
    $lines[] = '〒' . $site['postal_code'] . ' ' . $site['address'];
    $lines[] = 'TEL：' . $site['tel'];
    $lines[] = 'MAIL：' . $site['email'];

    return implode("\n", $lines);
}

function send_mail_utf8(
    string $to,
    string $subject,
    string $body,
    string $from,
    string $fromName,
    array $attachments = []
): bool {
    if (preg_match("/[\r\n]/", $to . $from)) {
        return false;
    }

    mb_language('Japanese');
    mb_internal_encoding('UTF-8');

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
    $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8');
    $boundary = '==Multipart_Boundary_' . bin2hex(random_bytes(16));
    $headers = [
        'From: ' . $encodedFromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
    ];

    if (empty($attachments)) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        return mb_send_mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }

    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $message = '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";

    foreach ($attachments as $attachment) {
        $path = $attachment['path'] ?? '';
        if (!is_file($path)) {
            continue;
        }

        $filename = basename((string)($attachment['filename'] ?? basename($path)));
        $mime = (string)($attachment['mime'] ?? 'application/octet-stream');
        $content = chunk_split(base64_encode((string)file_get_contents($path)));
        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: ' . $mime . '; name="' . mb_encode_mimeheader($filename, 'UTF-8') . '"' . "\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . mb_encode_mimeheader($filename, 'UTF-8') . '"' . "\r\n\r\n";
        $message .= $content . "\r\n";
    }

    $message .= '--' . $boundary . "--\r\n";
    return mb_send_mail($to, $encodedSubject, $message, implode("\r\n", $headers));
}

function delete_tmp_file(?string $path): void
{
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

function admin_is_logged_in(array $config): bool
{
    return false;
}

function admin_login(array $config, string $username, string $password): bool
{
    return false;
}

function admin_logout(): void
{
    // Legacy admission-only auth is disabled. Use /admin/logout.php.
}

function admission_storage_file(array $config): string
{
    return (string)($config['admin']['storage_file'] ?? '');
}

function load_admission_records(array $config): array
{
    return admission_load_records_from_db();
}

function save_admission_records(array $config, array $records): bool
{
    foreach ($records as $record) {
        if (is_array($record)) {
            admission_save_record_to_db($record);
        }
    }
    return true;
}

function find_admission_record(array $records, string $id): ?array
{
    foreach ($records as $record) {
        if ((string)($record['id'] ?? '') === $id) {
            return $record;
        }
    }
    return null;
}

function upsert_admission_record(array $config, array $record): bool
{
    admission_save_record_to_db($record);
    return true;
}

function load_legacy_admission_json_records(array $config): array
{
    $storageFile = admission_storage_file($config);
    if ($storageFile === '' || !is_file($storageFile)) {
        return [];
    }

    $records = json_decode((string)file_get_contents($storageFile), true);
    if (!is_array($records)) {
        return [];
    }

    usort($records, static fn(array $a, array $b): int => (int)($b['created_at_ts'] ?? 0) <=> (int)($a['created_at_ts'] ?? 0));
    return $records;
}

function build_admission_record(array $config, array $data, array $fees, array $photo = [], array $overrides = []): array
{
    $nowTs = time();
    $createdAtTs = isset($overrides['created_at_ts']) ? (int)$overrides['created_at_ts'] : $nowTs;

    return [
        'id' => (string)($overrides['id'] ?? ('adm_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)))),
        'status' => (string)($overrides['status'] ?? 'new'),
        'created_at' => (string)($overrides['created_at'] ?? date('Y-m-d H:i:s', $createdAtTs)),
        'created_at_ts' => $createdAtTs,
        'updated_at' => date('Y-m-d H:i:s', $nowTs),
        'updated_at_ts' => $nowTs,
        'admin_note' => (string)($overrides['admin_note'] ?? ''),
        'mail_status' => $overrides['mail_status'] ?? [],
        'photo' => $photo,
        'data' => $data,
        'fees' => $fees,
    ];
}

function admission_blank_data(): array
{
    return [
        'use_type' => 'new',
        'course' => 'basic',
        'main_member_status' => 'existing',
        'main_member_number' => '',
        'main_membership' => '',
        'addon' => 'basic',
        'initial_visits' => '',
        'start_date' => '',
        'campaign_code' => '',
        'procedure_date_1' => '',
        'procedure_time_1' => '',
        'procedure_date_2' => '',
        'procedure_time_2' => '',
        'procedure_date_3' => '',
        'procedure_time_3' => '',
        'surname' => '',
        'given_name' => '',
        'surname_kana' => '',
        'given_name_kana' => '',
        'name' => '',
        'kana' => '',
        'birth' => '',
        'birth_year' => '',
        'birth_month' => '',
        'birth_day' => '',
        'school_confirmation' => '',
        'gender' => '',
        'phone_type' => '',
        'phone' => '',
        'email' => '',
        'postal_code' => '',
        'prefecture' => '',
        'city_area' => '',
        'street_address' => '',
        'building' => '',
        'address' => '',
        'emergency_name' => '',
        'emergency_relationship' => '',
        'emergency_phone' => '',
        'guardian_name' => '',
        'health_checks' => [],
        'medical_memo' => '',
        'terms_agree' => '1',
        'photo_data' => '',
        'photo_token' => '',
        'remarks' => '',
    ];
}

function validate_admin_record(array $config, array $data): array
{
    $data['csrf_token'] = csrf_token();
    if (($data['initial_visits'] ?? '') === '') {
        $monthlyVisits = 0;
        if (($data['use_type'] ?? '') === 'add') {
            $addon = $config['pilates_addons'][$data['addon'] ?? ''] ?? null;
            $monthlyVisits = $addon ? (int)$addon['monthly_visits'] : 0;
        } else {
            $course = $config['pilates_courses'][$data['course'] ?? ''] ?? null;
            $monthlyVisits = $course ? (int)$course['monthly_visits'] : 0;
        }
        if ($monthlyVisits > 0) {
            $data['initial_visits'] = (string)max(initial_visit_options($config, $monthlyVisits));
        }
    }
    $errors = validate_form($config, $data, true);
    unset($errors['csrf'], $errors['terms_agree'], $errors['photo']);
    return array_values($errors);
}

function wareki_date_label(?string $date): string
{
    if (!$date) {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return $date;
    }

    $eras = [
        ['name' => '令和', 'start' => '2019-05-01'],
        ['name' => '平成', 'start' => '1989-01-08'],
        ['name' => '昭和', 'start' => '1926-12-25'],
        ['name' => '大正', 'start' => '1912-07-30'],
        ['name' => '明治', 'start' => '1868-01-25'],
    ];

    foreach ($eras as $era) {
        $start = new DateTimeImmutable($era['start']);
        if ($dt >= $start) {
            $year = (int)$dt->format('Y') - (int)$start->format('Y') + 1;
            return $era['name'] . ($year === 1 ? '元' : (string)$year) . '年' . $dt->format('n月j日');
        }
    }

    return $date;
}

function datetime_label(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }

    $timestamp = strtotime($datetime);
    return $timestamp === false ? $datetime : date('Y/m/d H:i', $timestamp);
}

function build_slim_copy_text(array $config, array $record): string
{
    $data = $record['data'] ?? [];
    $fees = $record['fees'] ?? [];
    $age = calculate_age($data['birth'] ?? '');
    $lines = [];
    $lines[] = '【SLIM転記用】';
    $lines[] = '受付ID：' . ($record['id'] ?? '');
    $lines[] = '受付日時：' . datetime_label($record['created_at'] ?? '');
    $lines[] = 'ステータス：' . (($config['admin']['status_options'][$record['status'] ?? ''] ?? ($record['status'] ?? '')));
    $lines[] = '';
    $lines[] = '会員氏名：' . ($data['name'] ?? '');
    $lines[] = 'フリガナ：' . ($data['kana'] ?? '');
    $lines[] = '生年月日（西暦）：' . date_label($data['birth'] ?? '');
    $lines[] = '生年月日（和暦）：' . wareki_date_label($data['birth'] ?? '');
    $lines[] = '年齢：' . ($age === null ? '' : $age . '歳');
    $lines[] = '電話番号種別：' . (($data['phone_type'] ?? '') === 'mobile' ? '携帯TEL' : (($data['phone_type'] ?? '') === 'home' ? '自宅TEL' : '未入力'));
    $lines[] = '電話番号：' . ($data['phone'] ?? '');
    $lines[] = 'メール：' . ($data['email'] ?? '');
    $lines[] = '郵便番号：' . ($data['postal_code'] ?? '');
    $lines[] = '住所：' . ($data['address'] ?? '');
    $lines[] = '緊急連絡先：' . ($data['emergency_name'] ?? '') . '（' . ($data['emergency_relationship'] ?? '') . '） ' . ($data['emergency_phone'] ?? '');
    $lines[] = '保護者氏名：' . ($data['guardian_name'] ?? '');
    $lines[] = '';
    $lines[] = '利用形態：' . use_type_label($data);
    $lines[] = '選択内容：' . ($fees['course_label'] ?? '');
    $lines[] = '月間利用回数：' . ($fees['monthly_visits'] ?? '') . '回';
    $lines[] = '初月計算区分：' . (string)($fees['proration']['label'] ?? '');
    $lines[] = '初月利用回数：' . ($fees['initial_visits'] ?? '') . '回';
    $lines[] = '通常月会費：' . yen((int)($fees['monthly_fee'] ?? 0));
    $lines[] = '初月会費合計：' . yen((int)($fees['current_month_fee'] ?? 0));
    if ((int)($fees['join_fee'] ?? 0) > 0) {
        $lines[] = '入会費：' . yen((int)$fees['join_fee']);
    }
    if (($data['use_type'] ?? '') === 'add') {
        $lines[] = '本館初月会費（日割）：' . yen((int)($fees['main_club_initial_fee'] ?? 0));
        $lines[] = 'Find Pilates初月会費：' . yen((int)($fees['pilates_current_month_fee'] ?? 0));
    }
    if ((int)($fees['processing_fee'] ?? 0) > 0) {
        $lines[] = '手数料：' . yen((int)$fees['processing_fee']);
    }
    if ((int)($fees['next_month_fee'] ?? 0) > 0) {
        $lines[] = '翌月会費：' . yen((int)$fees['next_month_fee']);
    }
    $lines[] = '初回概算合計：' . yen((int)($fees['initial_total'] ?? 0));
    $lines[] = '利用開始希望日：' . date_label($data['start_date'] ?? '');
    for ($i = 1; $i <= 3; $i++) {
        $lines[] = '第' . $i . '希望：' . date_label($data['procedure_date_' . $i] ?? '') . ' ' . time_slot_label($config, (string)($data['procedure_time_' . $i] ?? ''));
    }
    $lines[] = '健康補足：' . (string)($data['medical_memo'] ?? '');
    $lines[] = '受付メモ：' . (string)($record['admin_note'] ?? '');

    return implode("\n", $lines);
}

function archive_admission_photo(array $config, array $photo): array
{
    if (empty($photo['ok']) || empty($photo['path']) || !is_file((string)$photo['path'])) {
        return $photo;
    }

    $archiveDir = (string)($config['admin']['photo_archive_dir'] ?? '');
    if ($archiveDir === '') {
        return $photo;
    }

    ensure_dir($archiveDir);
    $sourcePath = (string)$photo['path'];
    $targetName = basename($sourcePath);
    $targetPath = rtrim($archiveDir, '/\\') . DIRECTORY_SEPARATOR . $targetName;

    if (@copy($sourcePath, $targetPath)) {
        $photo['archived_path'] = $targetPath;
        $photo['archived_filename'] = $targetName;
    }

    return $photo;
}
