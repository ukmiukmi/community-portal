<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

$username = $conn->real_escape_string($_SESSION['username']);
$role = $_SESSION['role'] ?? 'registrar';

// Fetch logged-in user details
$userQuery = $conn->query("
    SELECT u.id, u.username, u.full_name, u.assigned_community_id, c.name AS community_name
    FROM users u
    LEFT JOIN communities c ON u.assigned_community_id = c.id
    WHERE u.username = '{$username}'
");
$userData = $userQuery->fetch_assoc();
$loggedCommunityId = $userData['assigned_community_id'] ?? null;
$communityDefault  = $userData['community_name'] ?? '';

// Fetch communities
$communities = [];
$cRes = $conn->query("SELECT id, name FROM communities ORDER BY name ASC");
while ($c = $cRes->fetch_assoc()) {
    $communities[$c['id']] = $c['name'];
}

// Fetch villages
$villagesMap = [];
$vRes = $conn->query("SELECT id, community_id, name FROM villages ORDER BY name ASC");
while ($v = $vRes->fetch_assoc()) {
    $villagesMap[$v['community_id']][] = $v;
}

// Fetch citizens
if ($role === 'admin') {
    $citizensRes = $conn->query("SELECT * FROM citizens ORDER BY first_name ASC");
} else {
    $citizensRes = $conn->query("SELECT * FROM citizens WHERE community_id = {$loggedCommunityId} ORDER BY first_name ASC");
}

// Check GET preselect
$preselectCitizen = null;
if (isset($_GET['citizen_id']) && intval($_GET['citizen_id']) > 0) {
    $cid = intval($_GET['citizen_id']);
    if ($role === 'admin') {
        $citQuery = $conn->query("SELECT * FROM citizens WHERE id={$cid} LIMIT 1");
    } else {
        $citQuery = $conn->query("SELECT * FROM citizens WHERE id={$cid} AND community_id={$loggedCommunityId} LIMIT 1");
    }
    if ($citQuery && $citQuery->num_rows > 0) {
        $preselectCitizen = $citQuery->fetch_assoc();
    }
}

// Prepare JSON for JS
$villagesJSON = json_encode($villagesMap);
$preselectJSON = $preselectCitizen ? json_encode($preselectCitizen) : "null";
$role_js = json_encode($role);

// today's date
$today = date('Y-m-d');

$content = <<<HTML
<link rel="stylesheet" href="../css/issue_land_poa.css">

<div class="form-container">
<h2>Issue Land Power of Attorney</h2>

<form id="poaForm">

<label for="citizen_select">Select Citizen</label>
<select name="citizen_id" id="citizen_select" required>
    <option value="">-- Choose Citizen --</option>
HTML;

while ($c = $citizensRes->fetch_assoc()) {
    $selected = ($preselectCitizen && $preselectCitizen['id'] == $c['id']) ? 'selected' : '';
    $cFull = htmlspecialchars($c['first_name'] . ' ' . $c['last_name'], ENT_QUOTES);
    $cID = htmlspecialchars($c['citizen_id'], ENT_QUOTES);
    $phone = htmlspecialchars($c['phone'], ENT_QUOTES);
    $house = htmlspecialchars($c['house_address'], ENT_QUOTES);
    $commId = (int)$c['community_id'];
    $villId = (int)$c['village_id'];

    $content .= "
        <option value='{$c['id']}' {$selected}
            data-full='{$cFull}'
            data-phone='{$phone}'
            data-house='{$house}'
            data-community='{$commId}'
            data-village='{$villId}'
            data-citizenid='{$cID}'
        >{$cFull} ({$cID})</option>
    ";
}

$content .= <<<HTML
</select>

<div class="two-col">
    <div>
        <label for="full_name">Full Name</label>
HTML;

// Full name editable for admin
if ($role === 'admin') {
    $content .= '<input type="text" id="full_name" name="override_full_name">';
} else {
    $content .= '<input type="text" id="full_name" readonly class="disabled">';
}

$content .= <<<HTML
    </div>
    <div>
        <label for="phone">Phone</label>
HTML;

if ($role === 'admin') {
    $content .= '<input type="text" id="phone" name="override_phone">';
} else {
    $content .= '<input type="text" id="phone" readonly class="disabled">';
}

$content .= <<<HTML
    </div>
</div>

<label for="citizen_id">Citizen ID</label>
<input type="text" id="citizen_id" readonly class="disabled">

<label for="payment_amount">Payment Amount (₦)</label>
<input type="number" step="0.01" name="payment_amount" id="payment_amount" required>

<label for="community_field">Community</label>
HTML;

if ($role === "admin") {
    $content .= '<select id="community_field" name="community_id" required><option value="">-- Select Community --</option>';
    foreach ($communities as $cid => $cname) {
        $escapedName = htmlspecialchars($cname, ENT_QUOTES);
        $content .= "<option value='{$cid}'>{$escapedName}</option>";
    }
    $content .= '</select>';
} else {
    // Non-admin: display community name in readonly input (value is name)
    $content .= "<input type='text' id='community_field' value='" . htmlspecialchars($communityDefault, ENT_QUOTES) . "' readonly class='disabled'>";
}

$content .= <<<HTML

<label for="village_select">Village</label>
<select name="village_id" id="village_select" required>
    <option value="">-- Select Village --</option>
</select>

<label for="land_location">Land Location</label>
HTML;

// Land location editable for admin, readonly for registrar by requirement earlier (but user requested admin editable)
if ($role === "admin") {
    $content .= '<input type="text" name="land_location" id="land_location">';
} else {
    $content .= '<input type="text" name="land_location" id="land_location" readonly class="disabled">';
}

$content .= <<<HTML

<div class="two-col">
    <div>
        <label for="number_of_plots">Number of Plots</label>
        <input type="number" name="number_of_plots" id="number_of_plots" min="1" required>
    </div>
    <div>
        <label for="payment_date">Payment Date</label>
        <input type="date" name="payment_date" id="payment_date" value="{$today}" required>
    </div>
</div>

<button type="submit" id="poaSubmit">Issue Certificate</button>

</form>
</div>

<script>
window.poaVillages = $villagesJSON;
window.poaPreselect = $preselectJSON;
window.poaUserRole = $role_js;
</script>

<script src="../js/issue_land_poa.js"></script>

HTML;

include("../include/layout.php");
