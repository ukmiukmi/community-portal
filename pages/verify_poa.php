<?php
// --- DEBUG ---
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/db.php";

// --- HELPER: image to base64 ---
function imgDataUri($filename,$folder='') {
    $path = $folder ? rtrim($folder,'/\\').'/'.$filename : $filename;
    if (!$filename || !file_exists($path) || filesize($path)<5) return "";
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $data = base64_encode(file_get_contents($path));
    return "data:image/{$ext};base64,{$data}";
}

// --- GET SERIAL ---
$serial_no = isset($_GET['serial_no']) ? trim($_GET['serial_no']) : '';
if (!$serial_no) { echo "<h2>Missing serial number.</h2>"; exit; }

// --- FETCH TRANSACTION ---
$stmt = $conn->prepare("SELECT * FROM land_power_transactions WHERE serial_no=? LIMIT 1");
$stmt->bind_param("s",$serial_no);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$transaction) { echo "<h2>No record found.</h2>"; exit; }

// --- FETCH CITIZEN ---
$citizen_id = $transaction["citizen_id"];
$full_name = "Unknown"; $community_id = null;
$cit = $conn->prepare("SELECT first_name,last_name,community_id FROM citizens WHERE id=?");
$cit->bind_param("i",$citizen_id);
$cit->execute();
$res = $cit->get_result()->fetch_assoc();
$cit->close();
if ($res) { $full_name = trim($res["first_name"]." ".$res["last_name"]); $community_id = $res["community_id"]; }

// --- FETCH COMMUNITY ---
$coatData=$communityLogo=$sealData=""; $communityMotto="";
$communityFolder = __DIR__."/../uploads/community";
$signatureFolder = __DIR__."/../uploads/signatures";
$community_name = $transaction["community_name"];
$village_name = $transaction["town_union"];

if ($community_id) {
    $q=$conn->prepare("SELECT coat_of_arms,logo,stamp,motto FROM communities WHERE id=? LIMIT 1");
    $q->bind_param("i",$community_id);
    $q->execute();
    $branding = $q->get_result()->fetch_assoc();
    $q->close();
    if ($branding) {
        $coatData      = imgDataUri($branding["coat_of_arms"],$communityFolder);
        $communityLogo = imgDataUri($branding["logo"],$communityFolder);
        $sealData      = imgDataUri($branding["stamp"],$communityFolder);
        $communityMotto = $branding["motto"] ?: "";
        // --- DEBUG: check seal image ---
        // var_dump($branding["stamp"], $sealData); exit;
    }
}

// --- FETCH SIGNATURES ---
$presSigData = $secSigData = "";
if ($community_id) {
    $sig=$conn->prepare("SELECT role,file_path FROM signatures WHERE community_id=? AND role IN ('president','secretary')");
    $sig->bind_param("i",$community_id);
    $sig->execute();
    $sigRes=$sig->get_result();
    while($row=$sigRes->fetch_assoc()){
        if($row["role"]==="president") $presSigData = imgDataUri($row["file_path"],$signatureFolder);
        if($row["role"]==="secretary") $secSigData = imgDataUri($row["file_path"],$signatureFolder);
    }
    $sig->close();
}

// --- QR CODE ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?"https://":"http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\');
$qrUrl = $protocol.$host.$basePath."/verify_poa.php?serial_no=".urlencode($serial_no);
$qrDataUri="";
try {
    $qr=new \Endroid\QrCode\QrCode($qrUrl);
    if(method_exists($qr,'setSize')) $qr->setSize(200);
    if(method_exists($qr,'setMargin')) $qr->setMargin(5);
    $writer = new \Endroid\QrCode\Writer\PngWriter();
    $result = $writer->write($qr);
    $qrDataUri="data:image/png;base64,".base64_encode($result->getString());
}catch(Exception $e){}

// --- OTHER VARIABLES ---
$land_location   = $transaction["land_location"];
$number_of_plots = $transaction["number_of_plots"];
$payment_amount  = $transaction["payment_amount"];
$payment_date    = $transaction["payment_date"];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<title>Verify POA - <?=htmlspecialchars($serial_no)?></title>
<style>
body{font-family:'DejaVu Sans',Arial,sans-serif;background:#f7f7f7;margin:0;padding:0;}
.wrapper{display:flex;flex-wrap:wrap;justify-content:center;gap:16px;padding:12px;}
.card{background:#fff;box-shadow:0 6px 15px rgba(0,0,0,0.15);border-radius:6px;flex:1 1 500px;max-width:550px;position:relative;}
.card-inner{padding:16px;position:relative;}
.header{text-align:center;margin-bottom:8px;}
.title{font-size:18px;font-weight:bold;color:#0a501c;margin-bottom:2px;}
.sub{font-size:13px;font-weight:600;margin-bottom:2px;}
.motto{font-size:12px;font-style:italic;color:#444;margin-bottom:4px;}
.lead{font-size:14px;line-height:1.4;margin:4px 0;text-align:justify;}
.meta{font-size:13px;line-height:1.3;margin-top:4px;}
.sig-wrapper{display:flex;justify-content:space-between;margin-top:12px;}
.sig-block{text-align:center;width:48%;}
.sig-img{width:120px;max-width:100%;max-height:100px;margin-bottom:4px;}
.sig-line{border-top:1px solid #333;font-weight:700;padding-top:4px;}
.qr{text-align:center;margin-top:6px;}
.qr img{width:80px;}
.footer{font-size:12px;text-align:center;margin-top:8px;color:#333;}
.watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:28px;font-weight:900;color:#0a501c;opacity:0.05;pointer-events:none;z-index:0;}
.seal-watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:300px;opacity:0.08;pointer-events:none;z-index:0;}
.watermark-seal{position:absolute;width:360px;opacity:0.08;top:50%;left:50%;transform:translate(-50%, -50%);z-index:0;pointer-events:none;}
.wm-text{position:absolute;top:50%;left:50%;transform:translate(-50%, -50%) rotate(-30deg);font-size:32px;font-weight:900;color:#0a501c;opacity:0.04;z-index:1;pointer-events:none;}
</style>
</head>
<body>
<div class="wrapper">

<!-- RECEIPT CARD -->
<div class="card">
  <div class="card-inner">
    <?php if($sealData): ?>
        <img src="<?= $sealData ?>" class="watermark-seal" alt="Watermark Seal"/>
    <?php endif; ?>
    <div class="wm-text">OFFICIAL RECEIPT</div>
    <div class="header">
      <?= $communityLogo ? "<img src='{$communityLogo}' style='max-width:100px;margin-bottom:4px;'/>":"" ?>
      <div class="title"><?= strtoupper($community_name) ?> TOWN UNION</div>
      <div class="sub"><?= strtoupper($village_name) ?> VILLAGE</div>
      <div class="motto"><?= $communityMotto ? strtoupper($communityMotto) : "" ?></div>
    </div>
    <div class="lead"><strong>Receipt for Land Transaction</strong></div>
    <div class="meta">
      <strong>Name:</strong> <?= $full_name ?><br/>
      <strong>Land Location:</strong> <?= $land_location ?><br/>
      <strong>Plots:</strong> <?= $number_of_plots ?><br/>
      <strong>Amount Paid:</strong> &#8358;<?= number_format($payment_amount,2) ?><br/>
      <strong>Date:</strong> <?= $payment_date ?><br/>
      <strong>Serial No:</strong> <?= $serial_no ?>
    </div>
    <div class="sig-wrapper">
      <div class="sig-block"><?= $presSigData ? "<img src='{$presSigData}' class='sig-img'/>":"<div style='height:48px'></div>" ?><div class="sig-line">PRESIDENT OTU</div></div>
      <div class="sig-block"><?= $secSigData ? "<img src='{$secSigData}' class='sig-img'/>":"<div style='height:48px'></div>" ?><div class="sig-line">SECRETARY OTU</div></div>
    </div>
    <div class="qr"><?= $qrDataUri ? "<img src='{$qrDataUri}'/><br/>Verify Serial No: {$serial_no}":"" ?></div>
    <div class="footer">Verified at <?= $qrUrl ?></div>
  </div>
</div>

<!-- CERTIFICATE CARD -->
<div class="card">
  <div class="card-inner">
    <?php if($sealData): ?>
        <img src="<?= $sealData ?>" class="watermark-seal" alt="Watermark Seal"/>
    <?php endif; ?>
    <div class="wm-text">OFFICIAL CERTIFICATE</div>
    <div class="header">
      <?= $communityLogo ? "<img src='{$communityLogo}' style='max-width:100px;margin-bottom:4px;'/>":"" ?>
      <div class="title"><?= strtoupper($community_name) ?> TOWN UNION</div>
      <div class="sub"><?= strtoupper($village_name) ?> VILLAGE</div>
      <div class="motto"><?= $communityMotto ? strtoupper($communityMotto) : "" ?></div>
    </div>
    <div class="lead">
      This is to certify that <strong><?= $full_name ?></strong> of <strong><?= $land_location ?></strong> is duly authorized by <?= $community_name ?> Autonomous Community to take possession of the parcel of land mentioned herein.
    </div>
    <div class="lead">
      Land Location: <strong><?= $land_location ?></strong><br/>
      Date: <strong><?= $payment_date ?></strong><br/>
      Year: <strong><?= date('Y') ?></strong>
    </div>
    <div class="meta">
      <strong>Serial No:</strong> <?= $serial_no ?><br/>
      <strong>Plots:</strong> <?= $number_of_plots ?><br/>
      <strong>Amount Paid:</strong> &#8358;<?= number_format($payment_amount,2) ?><br/>
    </div>
    <div class="sig-wrapper">
      <div class="sig-block"><?= $presSigData ? "<img src='{$presSigData}' class='sig-img'/>":"<div style='height:48px'></div>" ?><div class="sig-line">PRESIDENT OTU</div></div>
      <div class="sig-block"><?= $secSigData ? "<img src='{$secSigData}' class='sig-img'/>":"<div style='height:48px'></div>" ?><div class="sig-line">SECRETARY OTU</div></div>
    </div>
    <div class="qr"><?= $qrDataUri ? "<img src='{$qrDataUri}'/><br/>Verify Serial No: {$serial_no}":"" ?></div>
    <div class="footer">Verified at <?= $qrUrl ?></div>
  </div>
</div>

</div>
</body>
</html>
