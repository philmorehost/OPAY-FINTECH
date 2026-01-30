<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) redirect('/login');

$pageTitle = 'Brute Force Protection';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) die('CSRF Failed');

    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $bruteforceSettings = [
            'period' => (int)$_POST['bf_period'],
            'max_failures_account' => (int)$_POST['bf_max_failures_account'],
            'max_failures_ip' => (int)$_POST['bf_max_failures_ip'],
            'block_duration' => $_POST['bf_block_duration'],
            'apply_local' => isset($_POST['bf_apply_local']) ? 1 : 0,
            'apply_remote' => isset($_POST['bf_apply_remote']) ? 1 : 0,
            'lock_admin' => isset($_POST['bf_lock_admin']) ? 1 : 0,
            'notify_unrecognized_ip' => isset($_POST['bf_notify_unrecognized_ip']) ? 1 : 0,
            'notify_brute_force' => isset($_POST['bf_notify_brute_force']) ? 1 : 0
        ];
        $stmt = $pdo->prepare("UPDATE settings SET bruteforceSettings = ? WHERE id = 1");
        $stmt->execute([json_encode($bruteforceSettings)]);
        $success = "Brute force settings updated!";
        $settings = fetchSettings($pdo);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_access') {
        $type = sanitize($_POST['type']);
        $value = sanitize($_POST['value']);
        $status = sanitize($_POST['status']);

        $stmt = $pdo->prepare("INSERT INTO access_control (type, value, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
        $stmt->execute([$type, $value, $status]);
        $success = "Access control updated for " . strtoupper($type) . ": $value";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
        $pdo->exec("DELETE FROM login_history WHERE createdAt < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $success = "Old logs cleared.";
    }
}

$bs = $settings['bruteforceSettings'] ?? [];
if (is_string($bs)) $bs = json_decode($bs, true) ?: [];

if (empty($bs)) {
    $bs = [
        'period' => 15,
        'max_failures_account' => 5,
        'max_failures_ip' => 10,
        'block_duration' => 'one-day',
        'apply_local' => 0,
        'apply_remote' => 1,
        'lock_admin' => 1,
        'notify_unrecognized_ip' => 1,
        'notify_brute_force' => 1
    ];
}

$countries = [
    "AF" => "Afghanistan", "AL" => "Albania", "DZ" => "Algeria", "AS" => "American Samoa", "AD" => "Andorra", "AO" => "Angola", "AI" => "Anguilla", "AQ" => "Antarctica", "AG" => "Antigua and Barbuda", "AR" => "Argentina", "AM" => "Armenia", "AW" => "Aruba", "AU" => "Australia", "AT" => "Austria", "AZ" => "Azerbaijan", "BS" => "Bahamas", "BH" => "Bahrain", "BD" => "Bangladesh", "BB" => "Barbados", "BY" => "Belarus", "BE" => "Belgium", "BZ" => "Belize", "BJ" => "Benin", "BM" => "Bermuda", "BT" => "Bhutan", "BO" => "Bolivia", "BA" => "Bosnia and Herzegovina", "BW" => "Botswana", "BV" => "Bouvet Island", "BR" => "Brazil", "IO" => "British Indian Ocean Territory", "BN" => "Brunei Darussalam", "BG" => "Bulgaria", "BF" => "Burkina Faso", "BI" => "Burundi", "KH" => "Cambodia", "CM" => "Cameroon", "CA" => "Canada", "CV" => "Cape Verde", "KY" => "Cayman Islands", "CF" => "Central African Republic", "TD" => "Chad", "CL" => "Chile", "CN" => "China", "CX" => "Christmas Island", "CC" => "Cocos (Keeling) Islands", "CO" => "Colombia", "KM" => "Comoros", "CG" => "Congo", "CD" => "Congo, the Democratic Republic of the", "CK" => "Cook Islands", "CR" => "Costa Rica", "CI" => "Cote d'Ivoire", "HR" => "Croatia", "CU" => "Cuba", "CY" => "Cyprus", "CZ" => "Czech Republic", "DK" => "Denmark", "DJ" => "Djibouti", "DM" => "Dominica", "DO" => "Dominican Republic", "EC" => "Ecuador", "EG" => "Egypt", "SV" => "El Salvador", "GQ" => "Equatorial Guinea", "ER" => "Eritrea", "EE" => "Estonia", "ET" => "Ethiopia", "FK" => "Falkland Islands (Malvinas)", "FO" => "Faroe Islands", "FJ" => "Fiji", "FI" => "Finland", "FR" => "France", "GF" => "French Guiana", "PF" => "French Polynesia", "TF" => "French Southern Territories", "GA" => "Gabon", "GM" => "Gambia", "GE" => "Georgia", "DE" => "Germany", "GH" => "Ghana", "GI" => "Gibraltar", "GR" => "Greece", "GL" => "Greenland", "GD" => "Grenada", "GP" => "Guadeloupe", "GU" => "Guam", "GT" => "Guatemala", "GN" => "Guinea", "GW" => "Guinea-Bissau", "GY" => "Guyana", "HT" => "Haiti", "HM" => "Heard Island and Mcdonald Islands", "VA" => "Holy See (Vatican City State)", "HN" => "Honduras", "HK" => "Hong Kong", "HU" => "Hungary", "IS" => "Iceland", "IN" => "India", "ID" => "Indonesia", "IR" => "Iran, Islamic Republic of", "IQ" => "Iraq", "IE" => "Ireland", "IL" => "Israel", "IT" => "Italy", "JM" => "Jamaica", "JP" => "Japan", "JO" => "Jordan", "KZ" => "Kazakhstan", "KE" => "Kenya", "KI" => "Kiribati", "KP" => "Korea, Democratic People's Republic of", "KR" => "Korea, Republic of", "KW" => "Kuwait", "KG" => "Kyrgyzstan", "LA" => "Lao People's Democratic Republic", "LV" => "Latvia", "LB" => "Lebanon", "LS" => "Lesotho", "LR" => "Liberia", "LY" => "Libyan Arab Jamahiriya", "LI" => "Liechtenstein", "LT" => "Lithuania", "LU" => "Luxembourg", "MO" => "Macao", "MK" => "Macedonia, the Former Yugoslav Republic of", "MG" => "Madagascar", "MW" => "Malawi", "MY" => "Malaysia", "MV" => "Maldives", "ML" => "Mali", "MT" => "Malta", "MH" => "Marshall Islands", "MQ" => "Martinique", "MR" => "Mauritania", "MU" => "Mauritius", "YT" => "Mayotte", "MX" => "Mexico", "FM" => "Micronesia, Federated States of", "MD" => "Moldova, Republic of", "MC" => "Monaco", "MN" => "Mongolia", "MS" => "Montserrat", "MA" => "Morocco", "MZ" => "Mozambique", "MM" => "Myanmar", "NA" => "Namibia", "NR" => "Nauru", "NP" => "Nepal", "NL" => "Netherlands", "AN" => "Netherlands Antilles", "NC" => "New Caledonia", "NZ" => "New Zealand", "NI" => "Nicaragua", "NE" => "Niger", "NG" => "Nigeria", "NU" => "Niue", "NF" => "Norfolk Island", "MP" => "Northern Mariana Islands", "NO" => "Norway", "OM" => "Oman", "PK" => "Pakistan", "PW" => "Palau", "PS" => "Palestinian Territory, Occupied", "PA" => "Panama", "PG" => "Papua New Guinea", "PY" => "Paraguay", "PE" => "Peru", "PH" => "Philippines", "PN" => "Pitcairn", "PL" => "Poland", "PT" => "Portugal", "PR" => "Puerto Rico", "QA" => "Qatar", "RE" => "Reunion", "RO" => "Romania", "RU" => "Russian Federation", "RW" => "Rwanda", "SH" => "Saint Helena", "KN" => "Saint Kitts and Nevis", "LC" => "Saint Lucia", "PM" => "Saint Pierre and Miquelon", "VC" => "Saint Vincent and the Grenadines", "WS" => "Samoa", "SM" => "San Marino", "ST" => "Sao Tome and Principe", "SA" => "Saudi Arabia", "SN" => "Senegal", "CS" => "Serbia and Montenegro", "SC" => "Seychelles", "SL" => "Sierra Leone", "SG" => "Singapore", "SK" => "Slovakia", "SI" => "Slovenia", "SB" => "Solomon Islands", "SO" => "Somalia", "ZA" => "South Africa", "GS" => "South Georgia and the South Sandwich Islands", "ES" => "Spain", "LK" => "Sri Lanka", "SD" => "Sudan", "SR" => "Suriname", "SJ" => "Svalbard and Jan Mayen", "SZ" => "Swaziland", "SE" => "Sweden", "CH" => "Switzerland", "SY" => "Syrian Arab Republic", "TW" => "Taiwan, Province of China", "TJ" => "Tajikistan", "TZ" => "Tanzania, United Republic of", "TH" => "Thailand", "TL" => "Timor-Leste", "TG" => "Togo", "TK" => "Tokelau", "TO" => "Tonga", "TT" => "Trinidad and Tobago", "TN" => "Tunisia", "TR" => "Turkey", "TM" => "Turkmenistan", "TC" => "Turks and Caicos Islands", "TV" => "Tuvalu", "UG" => "Uganda", "UA" => "Ukraine", "AE" => "United Arab Emirates", "GB" => "United Kingdom", "US" => "United States", "UM" => "United States Minor Outlying Islands", "UY" => "Uruguay", "UZ" => "Uzbekistan", "VU" => "Vanuatu", "VE" => "Venezuela", "VN" => "Viet Nam", "VG" => "Virgin Islands, British", "VI" => "Virgin Islands, U.s.", "WF" => "Wallis and Futuna", "EH" => "Western Sahara", "YE" => "Yemen", "ZM" => "Zambia", "ZW" => "Zimbabwe"
];

require_once __DIR__ . '/header.php';
?>

<div class="space-y-10 animate-fade-in pb-20 text-gray-900" x-data="{ bfTab: 'settings', countrySearch: '' }">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <h2 class="text-2xl font-black uppercase tracking-tight">Brute Force Protection</h2>
        <div class="flex gap-2 p-1 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto no-scrollbar max-w-full">
            <button @click="bfTab = 'settings'" :class="bfTab === 'settings' ? 'bg-gray-900 text-white shadow-lg' : 'text-gray-400'" class="px-6 py-2.5 rounded-xl font-black text-[10px] uppercase transition-all whitespace-nowrap">Settings</button>
            <button @click="bfTab = 'lists'" :class="bfTab === 'lists' ? 'bg-gray-900 text-white shadow-lg' : 'text-gray-400'" class="px-6 py-2.5 rounded-xl font-black text-[10px] uppercase transition-all whitespace-nowrap">White/Black Lists</button>
            <button @click="bfTab = 'history'" :class="bfTab === 'history' ? 'bg-gray-900 text-white shadow-lg' : 'text-gray-400'" class="px-6 py-2.5 rounded-xl font-black text-[10px] uppercase transition-all whitespace-nowrap">History</button>
        </div>
    </div>

    <?php if ($success): ?><div class="p-4 bg-green-50 text-green-800 rounded-2xl text-xs font-black border border-green-100 uppercase text-center shadow-sm"><?php echo $success; ?></div><?php endif; ?>

    <!-- Settings Tab -->
    <div x-show="bfTab === 'settings'" class="space-y-8 animate-fade-in">
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="save_settings">

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500 flex items-center gap-3"><i data-lucide="shield" class="w-5 h-5"></i> Username Protection</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-[10px] font-black text-gray-400 uppercase">Protection Period (min)</label><input type="number" name="bf_period" value="<?php echo $bs['period']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 border-none"></div>
                    <div><label class="text-[10px] font-black text-gray-400 uppercase">Max Failures (Account)</label><input type="number" name="bf_max_failures_account" value="<?php echo $bs['max_failures_account']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 border-none"></div>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                    <span class="text-[10px] font-black uppercase text-gray-500">Lock "admin" User</span>
                    <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="bf_lock_admin" value="1" class="sr-only peer" <?php echo $bs['lock_admin'] ? 'checked' : ''; ?>><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-red-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500 flex items-center gap-3"><i data-lucide="network" class="w-5 h-5"></i> IP Protection</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-[10px] font-black text-gray-400 uppercase">Max Failures (IP)</label><input type="number" name="bf_max_failures_ip" value="<?php echo $bs['max_failures_ip']; ?>" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 border-none"></div>
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase">Block Duration</label>
                        <select name="bf_block_duration" class="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 border-none outline-none text-xs">
                            <option value="one-day" <?php echo $bs['block_duration'] === 'one-day' ? 'selected' : ''; ?>>One Day</option>
                            <option value="one-week" <?php echo $bs['block_duration'] === 'one-week' ? 'selected' : ''; ?>>One Week</option>
                            <option value="one-month" <?php echo $bs['block_duration'] === 'one-month' ? 'selected' : ''; ?>>One Month</option>
                            <option value="one-year" <?php echo $bs['block_duration'] === 'one-year' ? 'selected' : ''; ?>>One Year</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                    <span class="text-[10px] font-black uppercase text-gray-500">Apply to Remote Addresses</span>
                    <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="bf_apply_remote" value="1" class="sr-only peer" <?php echo $bs['apply_remote'] ? 'checked' : ''; ?>><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-billpay-green after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
                </div>
            </div>

            <div class="md:col-span-2 bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500 flex items-center gap-3"><i data-lucide="bell" class="w-5 h-5"></i> Notifications</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                        <span class="text-[10px] font-black uppercase text-gray-500">Alert on unrecognized IP</span>
                        <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="bf_notify_unrecognized_ip" value="1" class="sr-only peer" <?php echo $bs['notify_unrecognized_ip'] ? 'checked' : ''; ?>><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-billpay-green after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                        <span class="text-[10px] font-black uppercase text-gray-500">Alert on Brute Force detection</span>
                        <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="bf_notify_brute_force" value="1" class="sr-only peer" <?php echo $bs['notify_brute_force'] ? 'checked' : ''; ?>><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-billpay-green after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="w-full py-6 bg-gray-900 text-white rounded-[32px] font-black uppercase shadow-xl tracking-widest hover:scale-[1.01] transition-all">Update Security Policy</button>
            </div>
        </form>
    </div>

    <!-- Lists Tab -->
    <div x-show="bfTab === 'lists'" class="space-y-10 animate-fade-in">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 text-indigo-500">Add to List</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_access">
                    <div class="grid grid-cols-2 gap-4">
                        <select name="type" class="p-4 bg-gray-50 rounded-2xl text-[10px] font-black uppercase border-none outline-none">
                            <option value="ip">IP Address</option>
                            <option value="user">Username</option>
                        </select>
                        <select name="status" class="p-4 bg-gray-50 rounded-2xl text-[10px] font-black uppercase border-none outline-none">
                            <option value="whitelisted">Whitelist</option>
                            <option value="blacklisted">Blacklist</option>
                        </select>
                    </div>
                    <input type="text" name="value" placeholder="Enter IP or Username" required class="w-full p-4 bg-gray-50 rounded-2xl font-bold border-none outline-none text-sm">
                    <button type="submit" class="w-full py-4 bg-billpay-green text-white rounded-2xl font-black uppercase text-[10px]">Add Entry</button>
                </form>
            </div>

            <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
                <h3 class="text-sm font-black uppercase tracking-widest mb-6 text-indigo-500">Manage Countries</h3>
                <div class="relative mb-6">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300"></i>
                    <input type="text" x-model="countrySearch" placeholder="Search country..." class="w-full pl-12 pr-4 py-4 bg-gray-50 rounded-2xl border-none outline-none text-[10px] font-black uppercase">
                </div>
                <div class="max-h-64 overflow-y-auto space-y-2 pr-2 scrollbar-hide">
                    <?php
                    $stmt = $pdo->query("SELECT value, status FROM access_control WHERE type = 'country'");
                    $activeCountries = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($countries as $code => $name):
                        $status = $activeCountries[$code] ?? 'not_specified';
                    ?>
                    <div x-show="'<?php echo strtolower($name); ?>'.includes(countrySearch.toLowerCase()) || '<?php echo strtolower($code); ?>'.includes(countrySearch.toLowerCase())" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="flex items-center gap-3"><span class="text-[10px] font-black text-gray-400"><?php echo $code; ?></span><span class="text-[11px] font-bold text-gray-800 uppercase"><?php echo $name; ?></span></div>
                        <form method="POST" class="flex gap-1">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_access">
                            <input type="hidden" name="type" value="country">
                            <input type="hidden" name="value" value="<?php echo $code; ?>">
                            <button name="status" value="whitelisted" class="p-2 rounded-lg <?php echo $status === 'whitelisted' ? 'bg-green-500 text-white' : 'bg-white text-gray-300 hover:text-green-500'; ?>"><i data-lucide="check-circle" class="w-4 h-4"></i></button>
                            <button name="status" value="blacklisted" class="p-2 rounded-lg <?php echo $status === 'blacklisted' ? 'bg-red-500 text-white' : 'bg-white text-gray-300 hover:text-red-500'; ?>"><i data-lucide="x-circle" class="w-4 h-4"></i></button>
                            <button name="status" value="not_specified" class="p-2 rounded-lg <?php echo $status === 'not_specified' ? 'bg-gray-400 text-white' : 'bg-white text-gray-300'; ?>"><i data-lucide="minus" class="w-4 h-4"></i></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
            <h3 class="text-sm font-black uppercase tracking-widest mb-8">Current Whitelist / Blacklist</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50"><th class="pb-4">Type</th><th class="pb-4">Value</th><th class="pb-4">Status</th><th class="pb-4">Activity</th><th class="pb-4 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php
                        $stmt = $pdo->query("SELECT * FROM access_control WHERE type != 'country' OR status != 'not_specified' ORDER BY createdAt DESC");
                        while ($row = $stmt->fetch()):
                        ?>
                        <tr>
                            <td class="py-4 font-black text-[10px] uppercase text-indigo-500"><?php echo $row['type']; ?></td>
                            <td class="py-4 font-bold text-xs">
                                <div class="flex items-center gap-2">
                                    <?php if($row['successCount'] >= 5 && $row['type'] === 'ip'): ?>
                                        <i data-lucide="crown" class="w-4 h-4 text-amber-500"></i>
                                    <?php endif; ?>
                                    <?php echo $row['value']; ?>
                                </div>
                            </td>
                            <td class="py-4"><span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $row['status'] === 'whitelisted' ? 'bg-green-50 text-green-600' : ($row['status'] === 'blacklisted' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-400'); ?>"><?php echo $row['status']; ?></span></td>
                            <td class="py-4 text-[10px] font-bold text-gray-400"><?php echo $row['successCount']; ?> Sessions</td>
                            <td class="py-4 text-right">
                                <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="action" value="update_access"><input type="hidden" name="type" value="<?php echo $row['type']; ?>"><input type="hidden" name="value" value="<?php echo $row['value']; ?>"><button name="status" value="not_specified" class="text-red-400 hover:text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- History Tab -->
    <div x-show="bfTab === 'history'" class="animate-fade-in">
        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-sm font-black uppercase tracking-widest">Login History (Last 30 Days)</h3>
                <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><button type="submit" name="action" value="clear_logs" class="text-[10px] font-black text-red-500 uppercase">Clear History</button></form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50"><th class="pb-4">User</th><th class="pb-4">IP Address</th><th class="pb-4">Status</th><th class="pb-4">Device</th><th class="pb-4 text-right">Time</th></tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php
                        $stmt = $pdo->query("SELECT l.*, u.username FROM login_history l LEFT JOIN users u ON l.userId = u.id ORDER BY l.createdAt DESC LIMIT 100");
                        while ($l = $stmt->fetch()):
                        ?>
                        <tr>
                            <td class="py-4">
                                <div class="font-black text-xs"><?php echo $l['username'] ?: ($l['attemptedUsername'] ?: 'Guest'); ?></div>
                                <?php if(!$l['username']): ?><div class="text-[8px] text-red-500 font-bold uppercase">Invalid Account</div><?php endif; ?>
                            </td>
                            <td class="py-4 font-mono text-[10px] text-gray-500"><?php echo $l['ip']; ?></td>
                            <td class="py-4"><span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $l['status'] === 'success' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>"><?php echo $l['status']; ?></span></td>
                            <td class="py-4 text-[9px] text-gray-400 max-w-[150px] truncate"><?php echo $l['userAgent']; ?></td>
                            <td class="py-4 text-right text-[10px] text-gray-400"><?php echo date('d M, H:i', strtotime($l['createdAt'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
