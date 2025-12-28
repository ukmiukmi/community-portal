<?php
session_start();
require_once __DIR__ . "/../vendor/autoload.php";
include __DIR__ . "/db.php";

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Dompdf\Dompdf;
use Dompdf\Options;

// --- Authentication ---
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

$username = $conn->real_escape_string($_SESSION['username']);
$role = $_SESSION['role'] ?? 'registrar';

// --- Helper ---
function post($k, $default = null) {
    return isset($_POST[$k]) ? $_POST[$k] : $default;
}

function imgDataUri($path) {
    if (file_exists($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $data = base64_encode(file_get_contents($path));
        return "data:image/{$ext};base64,{$data}";
    }
    return '';
}

// --- Sanitize inputs ---
$citizen_id      = intval(post('citizen_id', 0));
$community_name  = trim(post('community_name', ''));
$village_id      = intval(post('village_id', 0));
$land_location   = trim(post('land_location', ''));
$number_of_plots = intval(post('number_of_plots', 1));
$payment_amount  = floatval(post('payment_amount', 0));
$payment_date    = post('payment_date', date('Y-m-d'));
$issued_by       = $_SESSION['username'];
$status          = 'issued';
$isAjax          = isset($_POST['ajax']) && $_POST['ajax'] == 1;

// --- Basic validation ---
if (!$citizen_id || !$payment_amount) {
    if ($isAjax) {
        echo json_encode(['status'=>'error','message'=>'Missing citizen or amount.']); exit;
    }
    $_SESSION['action_error'] = "Missing citizen or amount.";
    header("Location: dashboard.php"); exit;
}

// --- Fetch citizen ---
$cstmt = $conn->prepare("SELECT * FROM citizens WHERE id = ?");
$cstmt->bind_param("i", $citizen_id);
$cstmt->execute();
$citizen = $cstmt->get_result()->fetch_assoc();
$cstmt->close();

if (!$citizen) {
    if ($isAjax) { echo json_encode(['status'=>'error','message'=>'Citizen not found.']); exit; }
    $_SESSION['action_error'] = "Citizen not found.";
    header("Location: dashboard.php"); exit;
}
$full_name = trim($citizen['first_name'] . ' ' . $citizen['last_name']);

// --- Resolve village ---
$village_name = '';
if ($village_id) {
    $vq = $conn->prepare("SELECT name FROM villages WHERE id = ?");
    $vq->bind_param("i", $village_id);
    $vq->execute();
    $vr = $vq->get_result()->fetch_assoc();
    $village_name = $vr['name'] ?? '';
    $vq->close();
}

// --- Resolve community ---
if (empty($community_name) && !empty($citizen['community_id'])) {
    $cm = $conn->prepare("SELECT name FROM communities WHERE id = ?");
    $cm->bind_param("i", $citizen['community_id']);
    $cm->execute();
    $cr = $cm->get_result()->fetch_assoc();
    $community_name = $cr['name'] ?? '';
    $cm->close();
}

// --- Serial & QR ---
$serial_no = "ENZ-LPOA-" . date('Y') . "-" . str_pad(mt_rand(1, 999999), 6, "0", STR_PAD_LEFT);
$barcode_string = $serial_no;

// --- Prepare folders ---
$baseDir = dirname(__DIR__);
$docDir  = $baseDir . "/documents";
$qrcDir  = $docDir . "/qrcodes";
if (!is_dir($docDir)) mkdir($docDir, 0755, true);
if (!is_dir($qrcDir)) mkdir($qrcDir, 0755, true);

// --- Generate QR ---
// --- Generate QR dynamically based on current host ---
$qrFile = $qrcDir . "/" . $serial_no . "_qr.png";
try {
    // Determine protocol
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // Build dynamic verify URL
    $qrUrl = $protocol . $host . $basePath . "/verify_poa.php?serial_no=" . urlencode($serial_no);

    $qrCode = new QrCode($qrUrl);
    if (method_exists($qrCode,'setSize')) $qrCode->setSize(300);
    if (method_exists($qrCode,'setMargin')) $qrCode->setMargin(10);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    file_put_contents($qrFile, $result->getString());
    $qrDataUri = "data:image/png;base64,".base64_encode($result->getString());
} catch(Exception $e){ $qrDataUri = ""; }


// --- Load images ---
$logoData = imgDataUri($baseDir."/images/logo.jpg");
// $sealData = imgDataUri($baseDir."/images/red_seal.png");

// --- Fetch dynamic signatures ---
// $signatures = $conn->query("SELECT role,file_path FROM signatures")->fetch_all(MYSQLI_ASSOC);
// $presSigData = $secSigData = "";
// foreach($signatures as $sig){
//     if($sig['role']=='president') $presSigData = imgDataUri($baseDir.'/'.$sig['file_path']);
//     if($sig['role']=='secretary') $secSigData = imgDataUri($baseDir.'/'.$sig['file_path']);
// }


// -------------------------------------------
// Load dynamic signatures based on community
// -------------------------------------------
// -------------------------------------------
// Load dynamic signatures based on community
// -------------------------------------------
$community_id = $citizen['community_id'] ?? null;

$presSigData = "";
$secSigData  = "";

if ($community_id) {

    $stmt = $conn->prepare("
        SELECT role, file_path 
        FROM signatures 
        WHERE community_id = ?
        AND role IN ('president', 'secretary')
        ORDER BY updated_at DESC
    ");
    $stmt->bind_param("i", $community_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['role'] === 'president' && !empty($row['file_path'])) {
            $presSigData = imgDataUri($baseDir . '/' . $row['file_path']);
        }
        if ($row['role'] === 'secretary' && !empty($row['file_path'])) {
            $secSigData = imgDataUri($baseDir . '/' . $row['file_path']);
        }
    }

    $stmt->close();
}

// end of fetch signtures
// --- Community coat/logo/motto ---
// --- Community coat/logo/motto/seal ---
$sealData = '';
$coatData = $communityLogo = $communityMotto = '';
$community_id = $citizen['community_id'] ?? 0;

if ($community_id) {
    $co = $conn->query("SELECT coat_of_arms, logo, stamp, motto FROM communities WHERE id=$community_id")->fetch_assoc();
    if ($co) {
        $coatData = !empty($co['coat_of_arms']) ? imgDataUri($baseDir.'/'.$co['coat_of_arms']) : '';
        $communityLogo = !empty($co['logo']) ? imgDataUri($baseDir.'/'.$co['logo']) : '';
        $communityMotto = $co['motto'] ?? '';
        $sealData = !empty($co['stamp']) ? imgDataUri($baseDir.'/'.$co['stamp']) : '';
    }
}


// --- File paths ---
$ts = time();
$certFilename    = "certificate_{$serial_no}_{$ts}.pdf";
$receiptFilename = "receipt_{$serial_no}_{$ts}.pdf";
$certFilePath    = "documents/".$certFilename;
$receiptFilePath = "documents/".$receiptFilename;
$certFullPath    = $docDir."/".$certFilename;
$receiptFullPath = $docDir."/".$receiptFilename;

// --- Certificate & Receipt HTML (trimmed versions for brevity, reuse your templates above) ---

$receipt_html = "
<!doctype html>
<html>
<head>
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'/>
<style>
  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    margin:0; padding:0; background:#fff; color:#000;
  }

  .page {
    padding:20px 26px;
    border:3px solid #0a501c;
    border-radius:8px;
    box-sizing:border-box;
    position:relative;
    overflow:hidden;
  }

  /* Ribbon bar top */
  .ribbon {
    width:100%;
    height:48px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    box-sizing:border-box;
    padding:6px 10px;
    position: relative;
  }
  .ribbon .stripe { flex:1; height:100%; }
  .stripe.green { background: #0a7a35; }
  .stripe.white { background: #ffffff; position: relative; }
  .coat-small {
    height: 80%;
    max-height: 40px;
    width: auto;
    position:absolute;
    top:50%;
    left:10px;
    transform:translateY(-50%);
    display:block;
  }

  /* Header */
  .header {
    text-align:center;
    margin-top:8px;
    position: relative;
    z-index:5;
  }
  .coat {
    width:auto;
    max-width:120px;
    max-height:80px;
    display:block;
    margin:0 auto 4px;
    object-fit:contain;
  }
  .title { font-size:18px; font-weight:bold; color:#0a501c; }
  .sub { font-weight:600; font-size:13px; }
  .motto { font-style:italic; font-size:11px; margin-bottom:8px; }
  h3 { font-size:16px; margin:8px 0 10px; text-decoration:underline; }

  /* QR code top-right */
  .qr-box {
    position:absolute;
    top:54px;
    right:20px;
    text-align:center;
    font-size:11px;
    z-index:5;
  }
  .qr { width:70px; margin-bottom:4px; }

  /* Watermark */
  .watermark-seal {
    position:absolute;
    width:360px;
    opacity:0.08;
    top:45%;
    left:50%;
    transform: translate(-50%, -50%);
    z-index:0;
    pointer-events:none;
  }
  .wm-text {
    position:absolute;
    top:48%;
    left:50%;
    transform:translate(-50%,-50%) rotate(-30deg);
    font-size:34px;
    font-weight:bold;
    color:#0a501c;
    opacity:0.04;
    z-index:0;
    pointer-events:none;
  }

  .content-zone { position:relative; z-index:5; margin-top:12px; }
  .info p { margin:4px 0; font-size:13px; }

  .signatures-wrapper {
    position: relative;
    margin-top:20px;
    height:120px;
  }
  .sign-pres, .sign-sec {
    position: absolute;
    bottom:0;
    text-align:center;
    width:38%;
  }
  .sign-pres { left:0; }
  .sign-sec { right:0; }
  .sig-img { width:120px; max-width:100%; max-height:100px; display:block; margin:0 auto 4px; }
  .sig-line { border-top:1px solid #333; width:70%; margin:0 auto 4px; padding-top:4px; font-weight:700; }

  .footer {
    margin-top:16px;
    font-size:11px;
    text-align:center;
    z-index:5;
    position:relative;
  }
</style>
</head>

<body>
<div class='page'>
  <!-- Ribbon bar -->
  <div class='ribbon'>
    <div class='stripe green'></div>
    <div class='stripe white'>
      " . ($coatData ? "<img src='{$coatData}' class='coat-small' alt='Coat'/>" : "") . "
    </div>
    <div class='stripe green'></div>
  </div>

  <!-- Watermark -->
  " . ($sealData ? "<img src='{$sealData}' class='watermark-seal' alt='seal'/>" : "") . "
  <div class='wm-text'>OFFICIAL RECEIPT</div>

  <!-- QR code -->
  " . ($qrDataUri ? "<div class='qr-box'><img src='{$qrDataUri}' class='qr' alt='qr'/><div>Verify QR</div></div>" : "") . "

  <!-- Header -->
  <div class='header'>
    " . ($communityLogo ? "<img src='{$communityLogo}' class='coat' alt='logo'/>" : ($logoData ? "<img src='{$logoData}' class='coat' alt='logo'/>" : "")) . "
    <div class='title'>" . strtoupper($community_name) . " TOWN UNION</div>
    <div class='sub'>" . strtoupper($village_name) . " VILLAGE</div>
    <div class='sub'>EZZA NORTH LGA, EBIAJI, EBONYI STATE</div>
    <div class='motto'>" . (!empty($communityMotto) ? strtoupper($communityMotto) : "MOTTO: PEACE, UNITY & DEVELOPMENT") . "</div>
    <h3>OFFICIAL PAYMENT RECEIPT</h3>
  </div>

  <div class='content-zone'>
    <div class='info'>
      <p><strong>Received From:</strong> {$full_name}</p>
      <p><strong>Purpose:</strong> Land Power of Attorney</p>
      <p><strong>Location:</strong> {$land_location}</p>
      <p><strong>Plots:</strong> {$number_of_plots}</p>
      <p><strong>Amount Paid:</strong> &#8358;" . number_format($payment_amount,2) . "</p>
      <p><strong>Date Paid:</strong> {$payment_date}</p>
      <p><strong>Serial No:</strong> {$serial_no}</p>
    </div>

    <div class='signatures-wrapper'>
      <div class='sign-pres'>
        " . ($presSigData ? "<img src='{$presSigData}' class='sig-img' alt='pres sig'/>" : "<div style='height:46px'></div>") . "
        <div class='sig-line'>PRESIDENT OTU</div>
      </div>
      <div class='sign-sec'>
        " . ($secSigData ? "<img src='{$secSigData}' class='sig-img' alt='sec sig'/>" : "<div style='height:46px'></div>") . "
        <div class='sig-line'>SECRETARY OTU</div>
      </div>
    </div>

    <div class='footer'>
      Verify at {$qrUrl}
    </div>
  </div>
</div>
</body>
</html>
";
$certificate_html = "
<!doctype html>
<html>
<head>
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'/>
<style>
  @page { margin:0; size:A4 landscape; }
  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    margin:0; padding:0; background:#f0f0ef;
    -webkit-print-color-adjust: exact;
  }

  .canvas { width:100%; display:flex; justify-content:center; padding:8px; box-sizing:border-box; }
  .frame { width:100%; max-width:1100px; background: linear-gradient(180deg,#fff,#fbfbf8); padding:8px; box-sizing:border-box; position:relative; box-shadow:0 10px 20px rgba(0,0,0,0.2); page-break-inside: avoid; break-inside: avoid; }
  .frame-border { border:12px solid #0a501c; padding:4px; box-sizing:border-box; }
  .frame-inner { border:4px solid #c79b1a; padding:20px 24px; box-sizing:border-box; background:linear-gradient(180deg,#fffefc,#ffffff); position: relative; }

  .corner { position:absolute; width:48px; height:48px; z-index:30; pointer-events:none; }
  .corner.tl { left:4px; top:4px; transform: rotate(0deg); }
  .corner.tr { right:4px; top:4px; transform: rotate(90deg); }
  .corner.bl { left:4px; bottom:4px; transform: rotate(270deg); }
  .corner.br { right:4px; bottom:4px; transform: rotate(180deg); }

  .ribbon { width:100%; height:50px; display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:4px; position: relative; }
  .ribbon .stripe { flex:1; height:100%; }
  .stripe.green { background:#0a7a35; }
  .stripe.white { display:flex; align-items:center; justify-content:center; background:#fff; position: relative; }
  .coat-small { height:80%; max-height:40px; width:auto; position:absolute; top:50%; left:10px; transform:translateY(-50%); display:block; }

  .watermark-seal { position:absolute; width:220px; opacity:0.05; top:52%; left:50%; transform:translate(-50%, -50%); z-index:0; pointer-events:none; }
  .wm-text { position:absolute; top:52%; left:50%; transform:translate(-50%, -50%) rotate(-30deg); font-size:28px; font-weight:700; color:#0a501c; opacity:0.04; z-index:0; pointer-events:none; }

  .content { position:relative; z-index:5; padding:4px 8px; color:#111; word-wrap:break-word; white-space:normal; }
  .header { text-align:center; margin-bottom:4px; }
  .coat { width:auto; max-width:120px; max-height:80px; display:block; margin:0 auto 4px; object-fit:contain; }
  .title { font-size:18px; font-weight:bold; color:#0a501c; }
  .sub { font-weight:600; font-size:13px; }
  .motto { font-size:12px; font-style:italic; margin-bottom:4px; color:#444; }
  .cert-title { font-size:26px; font-weight:900; margin:6px 0 4px; text-decoration:underline; color:#000; }
  .lead { font-size:14px; line-height:1.4; margin:4px 0; text-align:justify; }
  .meta { margin-top:4px; font-size:13px; line-height:1.3; }

  .signatures-wrapper { position: relative; margin-top: 8px; height: 120px; }
  .sign-pres { position: absolute; left: 0; bottom: 0; text-align: center; width: 38%; }
  .sign-sec { position: absolute; right: 0; bottom: 0; text-align: center; width: 38%; }
  .sig-img { width: 120px; max-width:100%; max-height:100px; display:block; margin:0 auto 4px; }
  .sig-line { border-top: 1px solid #333; width:70%; margin:0 auto 4px; padding-top:4px; font-weight:700; }

  .qr-block { position:absolute; top:20px; right:20px; text-align:center; font-size:10px; }
  .qr-block img { width:70px; margin-bottom:2px; }
  .footer { text-align:center; margin-top:6px; font-size:12px; color:#333; }

  @media print {
    body * { visibility: hidden !important; }
    .page, .page * { visibility: visible !important; }
    .page { margin:0; box-shadow:none !important; width:100%; }
  }
</style>
</head>
<body>
<div class='canvas'>
  <div class='frame'>
    <div class='frame-border'>
      <div class='frame-inner'>
        <div class='corner tl'></div>
        <div class='corner tr'></div>
        <div class='corner bl'></div>
        <div class='corner br'></div>

        <!-- Ribbon -->
        <div class='ribbon'>
          <div class='stripe green'></div>
          <div class='stripe white'>" . ($coatData ? "<img src='{$coatData}' class='coat-small' alt='coat'/>" : "") . "</div>
          <div class='stripe green'></div>
        </div>
        " . ($sealData ? "<img src='{$sealData}' class='watermark-seal'/>" : "") . "
        <div class='wm-text'>OFFICIAL CERTIFICATE</div>
        " . ($qrDataUri ? "<div class='qr-block'><img src='{$qrDataUri}'/><div>{$barcode_string}</div></div>" : "") . "

        <div class='content'>
          <div class='header'>
            " . ($communityLogo ? "<img src='{$communityLogo}' class='coat' alt='logo'/>" : ($logoData ? "<img src='{$logoData}' class='coat' alt='logo'/>" : "")) . "
            <div class='title'>" . strtoupper($community_name) . " TOWN UNION</div>
            <div class='sub'>" . strtoupper($village_name) . " VILLAGE</div>
            <div class='sub'>EZZA NORTH LGA, EBIAJI, EBONYI STATE</div>
            <div class='motto'>" . (!empty($communityMotto) ? strtoupper($communityMotto) : "MOTTO: PEACE, UNITY & DEVELOPMENT") . "</div>
            <div class='cert-title'>LAND POWER OF ATTORNEY</div>
          </div>

          <div class='lead'>
            Chief, Dr., Hon., Mr. & Mrs. of <strong>{$full_name}</strong> of <strong>{$land_location}</strong> is duly appointed by Okposhi Autonomous Community to take possession and hold all that parcel of Land.
          </div>
          <div class='lead'>
            Situated at <strong>{$land_location}</strong> Date <strong>{$payment_date}</strong> Day of <strong>" . date('Y') . "</strong>
          </div>
          <div class='lead'>
            We therefore by the legal right and power conferred on the members of Okposhi Town Union/ Okposhi Autonomous Community Land Power of Attorney, having duly investigated on the vendor-purchaser rights of selling and purchasing now is caused to approve the attached documents/property as genuine. For and on behalf of Okposhi Autonomous Community.
          </div>

          <div class='meta'>
            <strong>Serial No:</strong> {$serial_no} &nbsp;&nbsp;
            <strong>Plots:</strong> {$number_of_plots} &nbsp;&nbsp;
            <strong>Amount:</strong> &#8358;" . number_format($payment_amount,2) . " &nbsp;&nbsp;
            <strong>Date:</strong> {$payment_date}
          </div>

          <div class='signatures-wrapper'>
            <div class='sign-pres'>
              " . ($presSigData ? "<img src='{$presSigData}' class='sig-img'/>" : "<div style='height:48px'></div>") . "
              <div class='sig-line'>PRESIDENT OTU</div>
            </div>
            <div class='sign-sec'>
              " . ($secSigData ? "<img src='{$secSigData}' class='sig-img'/>" : "<div style='height:48px'></div>") . "
              <div class='sig-line'>SECRETARY OTU</div>
            </div>
          </div>

          <div class='footer'>
            Verify at {$qrUrl}
          </div>
        </div> <!-- content -->
      </div> <!-- frame-inner -->
    </div> <!-- frame-border -->
  </div> <!-- frame -->
</div> <!-- canvas -->
</body>
</html>
";

// end of cert and receipt

// --- Generate PDFs ---
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

try{
    $dompdf1 = new Dompdf($options);
    $dompdf1->loadHtml($certificate_html);
    $dompdf1->setPaper('A4','landscape');
    $dompdf1->render();
    file_put_contents($certFullPath, $dompdf1->output());

    $dompdf2 = new Dompdf($options);
    $dompdf2->loadHtml($receipt_html);
    $dompdf2->setPaper('A4','portrait');
    $dompdf2->render();
    file_put_contents($receiptFullPath, $dompdf2->output());
} catch(Exception $e){
    if($isAjax) { echo json_encode(['status'=>'error','message'=>'PDF generation failed']); exit; }
    $_SESSION['action_error'] = "PDF generation failed: ".$e->getMessage();
    header("Location: dashboard.php"); exit;
}

// --- Insert DB ---
$insert = $conn->prepare("
    INSERT INTO land_power_transactions
    (citizen_id, serial_no, barcode_string, payment_amount, payment_date, issued_by, certificate_file, receipt_file, community_name, town_union, land_location, number_of_plots, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
");
$insert->bind_param("issdsssssssis",
    $citizen_id,$serial_no,$barcode_string,$payment_amount,$payment_date,$issued_by,
    $certFilePath,$receiptFilePath,$community_name,$village_name,$land_location,$number_of_plots,$status
);
if(!$insert->execute()){
    if($isAjax){ echo json_encode(['status'=>'error','message'=>'Failed to save transaction']); exit; }
    $_SESSION['action_error'] = 'Failed to save transaction: '.$insert->error;
    header('Location: dashboard.php'); exit;
}

// --- Return ---
// if($isAjax){
//     echo json_encode(['status'=>'success','file'=>$certFilePath]);
//     exit;
// }

if($isAjax){
    // Make URLs relative to web root (outside pages folder)
    $certUrl   = '/otu/documents/' . $certFilename;
    $receiptUrl = '/otu/documents/' . $receiptFilename;

    echo json_encode([
        'status' => 'success',
        'certificate' => $certUrl,
        'receipt' => $receiptUrl
    ]);
    exit;
}


// end of return 

$_SESSION['success_message'] = "POA Certificate & Receipt issued for {$full_name} (Serial: {$serial_no}).";
header('Location: dashboard.php');
exit;
