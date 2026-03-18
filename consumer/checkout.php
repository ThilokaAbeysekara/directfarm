<?php

require_once '../includes/config.php';
requireRole('consumer', '../index.php');

$db = getDB();

// Fetch cart
$stmt = $db->prepare("SELECT c.*, p.name, p.price, p.unit, p.image, p.farmer_id, p.stock FROM cart c JOIN products p ON c.product_id=p.id WHERE c.consumer_id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($cartItems)) { header("Location: cart.php"); exit; }

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));

// Zone-based delivery fee (matches logistics.php)
$expressZone  = ['Colombo','Gampaha','Kalutara','Kandy','Galle','Matara'];
$standardZone = ['Nuwara Eliya','Matale','Badulla','Ratnapura','Kegalle','Hambantota','Kurunegala'];
$extendedZone = ['Jaffna','Kilinochchi','Mannar','Vavuniya','Trincomalee','Batticaloa','Ampara','Puttalam','Anuradhapura','Polonnaruwa','Monaragala'];

// Get consumer's district - from POST (delivery district dropdown) or profile
$userDistrict = clean($_POST['delivery_district'] ?? '');
if (!$userDistrict) {
    $dStmt = $db->prepare("SELECT district FROM users WHERE id=?");
    $dStmt->bind_param("i", $_SESSION['user_id']);
    $dStmt->execute();
    $dRow = $dStmt->get_result()->fetch_assoc();
    $userDistrict = $dRow['district'] ?? '';
}

// Calculate fee based on zone
if (in_array($userDistrict, $expressZone)) {
    $zoneName    = 'Express Zone';
    $zoneFee     = 150;
    $freeThresh  = 1000;
} elseif (in_array($userDistrict, $standardZone)) {
    $zoneName    = 'Standard Zone';
    $zoneFee     = 200;
    $freeThresh  = 1000;
} elseif (in_array($userDistrict, $extendedZone)) {
    $zoneName    = 'Extended Zone';
    $zoneFee     = 350;
    $freeThresh  = 1500;
} else {
    $zoneName    = 'Standard Zone';
    $zoneFee     = 200;
    $freeThresh  = 1000;
}

$delivery = $subtotal >= $freeThresh ? 0 : $zoneFee;
$total    = $subtotal + $delivery;

// Handle order placement
$orderMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address  = clean($_POST['address'] ?? '');
    $phone    = clean($_POST['phone'] ?? '');
    $payment  = in_array($_POST['payment'] ?? '', ['card','bank_transfer','cash_on_delivery']) ? $_POST['payment'] : 'cash_on_delivery';
    $notes    = clean($_POST['notes'] ?? '');
    $fullAddr = "$address | Phone: $phone";

    // Bank transfer proof upload
    $bankProofPath = '';
    if ($payment === 'bank_transfer') {
        if (empty($_FILES['bank_proof']['name'])) {
            $orderMsg = 'error:Please upload your bank transfer receipt before placing the order.';
            goto skip_order;
        }
        $allowed = ['image/jpeg','image/png','image/jpg','image/gif'];
        if (!in_array($_FILES['bank_proof']['type'], $allowed)) {
            $orderMsg = 'error:Please upload a valid image file (JPG, PNG).';
            goto skip_order;
        }
        $ext  = pathinfo($_FILES['bank_proof']['name'], PATHINFO_EXTENSION);
        $fname = 'proof_' . time() . '_' . $_SESSION['user_id'] . '.' . $ext;
        $dest  = '../uploads/bank_proofs/' . $fname;
        if (!move_uploaded_file($_FILES['bank_proof']['tmp_name'], $dest)) {
            $orderMsg = 'error:Failed to upload receipt. Please try again.';
            goto skip_order;
        }
        $bankProofPath = $fname;
    }

    // Card validation
    if ($payment === 'card') {
        $cardName   = clean($_POST['card_name']   ?? '');
        $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $cardExpiry = clean($_POST['card_expiry'] ?? '');
        $cardCvv    = clean($_POST['card_cvv']    ?? '');
        if (!$cardName || strlen($cardNumber) < 13 || strlen($cardCvv) < 3 || !$cardExpiry) {
            $orderMsg = 'error:Please enter valid card details.';
            goto skip_order;
        }
    }

    if (empty($address) || empty($phone)) {
        $orderMsg = 'error:Please fill in your delivery address and phone number.';
    skip_order:
    } else {
        // Insert order
    
        if ($bankProofPath) $notes = $notes ? $notes . ' | bank_proof:' . $bankProofPath : 'bank_proof:' . $bankProofPath;

        $ostmt = $db->prepare("INSERT INTO orders (consumer_id, total_amount, delivery_address, payment_method, notes) VALUES (?,?,?,?,?)");
        if (!$ostmt) { $orderMsg = 'error:DB error: ' . $db->error; }
        else {
            $ostmt->bind_param("idsss", $_SESSION['user_id'], $total, $fullAddr, $payment, $notes);
            $ostmt->execute();
            $orderId = $db->insert_id;

            // Insert order items
            foreach ($cartItems as $item) {
                $subtotalItem = $item['price'] * $item['quantity'];
                $farmerId = (int)($item['farmer_id'] ?? 0);
                $iStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, farmer_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?,?)");
                if ($iStmt) {
                    $iStmt->bind_param("iiiddd", $orderId, $item['product_id'], $farmerId, $item['quantity'], $item['price'], $subtotalItem);
                    $iStmt->execute();
                }
                // Reduce stock
                $db->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$item['product_id']}");
            }

            // Clear cart
            $cstmt = $db->prepare("DELETE FROM cart WHERE consumer_id=?");
            if ($cstmt) { $cstmt->bind_param("i", $_SESSION['user_id']); $cstmt->execute(); }

            // Redirect to success
            header("Location: order_success.php?order_id=$orderId");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Checkout - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<h2 class="page-title" style="padding:25px 5% 5px;">Checkout</h2>

<?php if ($orderMsg): list($type,$msg) = explode(':', $orderMsg, 2); ?>
<div style="background:<?= $type==='success'?'#e0ffe8':'#ffe0e0' ?>; color:<?= $type==='success'?'#27ae60':'#c0392b' ?>; padding:12px 5%; font-weight:500;"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="checkout-container" style="padding:0 5%;">
    <form method="POST" id="checkoutForm" enctype="multipart/form-data">
    <div class="checkout-left">
        <!-- Delivery Info -->
        <div class="card">
            <h3><i class="fa-solid fa-truck"></i> Delivery Information</h3>
            <div class="two-col" style="margin-top:15px;">
                <div>
                    <label>Full Name</label>
                    <input type="text" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" readonly style="background:#f0f0f0;">
                </div>
                <div>
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" placeholder="+94 77 123 4567" required>
                </div>
            </div>
            <label>Delivery Address *</label>
            <textarea name="address" rows="3" placeholder="House No, Street, City" required></textarea>
            </div>
            <div style="margin-bottom:16px;">
            <label style="font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:6px;">Delivery District * <small style="color:#009933;font-weight:400;" id="zoneLabel"></small></label>
            <select name="delivery_district" id="deliveryDistrict" required onchange="updateDeliveryFee(this.value)" style="width:100%;padding:13px 16px;border:1.5px solid #eee;border-radius:12px;font-size:14px;background:#fafafa;">
                <option value="">-- Select District --</option>
                <?php
                $allDistricts = ['Colombo','Gampaha','Kalutara','Kandy','Galle','Matara',
                    'Nuwara Eliya','Matale','Badulla','Ratnapura','Kegalle','Hambantota','Kurunegala',
                    'Jaffna','Kilinochchi','Mannar','Vavuniya','Trincomalee','Batticaloa','Ampara',
                    'Puttalam','Anuradhapura','Polonnaruwa','Monaragala'];
                foreach ($allDistricts as $d):
                ?>
                <option value="<?= $d ?>" <?= $userDistrict===$d?'selected':'' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
            <label>Special Notes (optional)</label>
            <textarea name="notes" rows="2" placeholder="Any instructions for delivery..."></textarea>
        </div>

        <!-- Payment -->
        <div class="card">
            <h3><i class="fa-solid fa-credit-card"></i> Payment Method</h3>
            <div style="margin-top:15px;">
                <div class="payment-box" onclick="selectPayment('cash_on_delivery',this)">
                    <label><input type="radio" name="payment" value="cash_on_delivery" id="pay_cash" checked onchange="showCardForm(this.value)"> 💵 Cash on Delivery</label>
                </div>
                <div class="payment-box" onclick="selectPayment('bank_transfer',this)">
                    <label><input type="radio" name="payment" value="bank_transfer" id="pay_bank" onchange="showCardForm(this.value)"> 🏦 Bank Transfer</label>
                </div>
                <div class="payment-box" onclick="selectPayment('card',this)">
                    <label><input type="radio" name="payment" value="card" id="pay_card" onchange="showCardForm(this.value)"> 💳 Credit/Debit Card</label>
                </div>
            </div>

            <!-- Card Details Form (shown only when card is selected) -->
            <div id="cardDetails" style="display:none; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                    <!-- Mastercard -->
                    <svg width="38" height="24" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg">
                        <rect width="38" height="24" rx="4" fill="#f5f5f5"/>
                        <circle cx="15" cy="12" r="7" fill="#EB001B"/>
                        <circle cx="23" cy="12" r="7" fill="#F79E1B"/>
                        <path d="M19 6.8a7 7 0 0 1 0 10.4A7 7 0 0 1 19 6.8z" fill="#FF5F00"/>
                    </svg>
                    <!-- Visa -->
                    <svg width="48" height="24" viewBox="0 0 48 24" xmlns="http://www.w3.org/2000/svg">
                        <rect width="48" height="24" rx="4" fill="#1A1F71"/>
                        <text x="7" y="17" font-family="Arial" font-size="13" font-weight="bold" fill="white" font-style="italic">VISA</text>
                    </svg>
                    <span style="font-size:12px; color:#aaa;">Secured payment</span>
                    <i class="fa-solid fa-lock" style="color:#009933; font-size:12px;"></i>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:6px;">Cardholder Name</label>
                    <input type="text" id="cardName" placeholder="Name on card" style="width:100%; padding:12px 14px; border:1.5px solid #eee; border-radius:10px; font-size:14px; background:#fafafa;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:6px;">Card Number</label>
                    <div style="position:relative;">
                        <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCardNumber(this)" style="width:100%; padding:12px 44px 12px 14px; border:1.5px solid #eee; border-radius:10px; font-size:14px; background:#fafafa; letter-spacing:2px;">
                        <i class="fa-solid fa-credit-card" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); color:#ccc;"></i>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:6px;">Expiry Date</label>
                        <input type="text" id="cardExpiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)" style="width:100%; padding:12px 14px; border:1.5px solid #eee; border-radius:10px; font-size:14px; background:#fafafa;">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:6px;">CVV</label>
                        <div style="position:relative;">
                            <input type="text" id="cardCvv" placeholder="123" maxlength="3" inputmode="numeric" pattern="[0-9]*" style="width:100%; padding:12px 44px 12px 14px; border:1.5px solid #eee; border-radius:10px; font-size:14px; background:#fafafa;">
                            <button type="button" onclick="toggleCvv()" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#aaa;"><i class="fa-solid fa-eye" id="cvvEye"></i></button>
                        </div>
                    </div>
                </div>
                <div id="cardError" style="display:none; background:#fce4e4; color:#c62828; padding:10px 14px; border-radius:8px; font-size:13px; margin-top:8px;"></div>
            </div>

            <!-- Bank Transfer Info + Upload -->
            <div id="bankDetails" style="display:none; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <div style="background:#f0f7f0; border-radius:12px; padding:18px; margin-bottom:16px;">
                    <p style="font-size:14px; font-weight:700; color:#1a2e1a; margin-bottom:10px;">🏦 Bank Transfer Details</p>
                    <p style="font-size:13px; color:#555; line-height:2;">
                        Bank: <strong>Bank of Ceylon</strong><br>
                        Account Name: <strong>DirectFarm LK</strong><br>
                        Account No: <strong>0123456789</strong><br>
                        Branch: <strong>Colombo Main</strong>
                    </p>
                    <p style="font-size:12px; color:#888; margin-top:8px;">Please use your name as the payment reference.</p>
                </div>
                <div style="border:2px dashed #009933; border-radius:12px; padding:20px; text-align:center; background:#fafff8; cursor:pointer;" onclick="document.getElementById('bankProofInput').click();" id="uploadZone">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px; color:#009933; margin-bottom:10px; display:block;"></i>
                    <p style="font-size:14px; font-weight:700; color:#1a2e1a; margin-bottom:4px;">Upload Transfer Receipt</p>
                    <p style="font-size:12px; color:#888;">Click to select your receipt photo (JPG, PNG)</p>
                    <input type="file" id="bankProofInput" name="bank_proof" accept="image/*" style="display:none;" onchange="previewReceipt(this)">
                </div>
                <div id="receiptPreview" style="display:none; margin-top:14px; text-align:center;">
                    <img id="previewImg" src="" style="max-width:100%; max-height:200px; border-radius:10px; border:2px solid #009933;">
                    <p style="font-size:12px; color:#27ae60; margin-top:8px; font-weight:600;">✓ Receipt uploaded — you can now place your order</p>
                </div>
                <div id="bankProofError" style="display:none; background:#fce4e4; color:#c62828; padding:10px 14px; border-radius:8px; font-size:13px; margin-top:10px;"></div>
            </div>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="checkout-right">
        <div class="card">
            <h3>Order Summary</h3>
            <div style="max-height:260px; overflow-y:auto;">
                <?php foreach ($cartItems as $item): ?>
                <div style="display:flex; align-items:center; gap:10px; margin:10px 0; border-bottom:1px solid #f5f5f5; padding-bottom:10px;">
                    <img src="../<?= htmlspecialchars($item['image'] ?? '') ?>" style="width:50px; height:50px; object-fit:cover; border-radius:8px;">
                    <div style="flex:1; font-size:14px;">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                        <br>x<?= $item['quantity'] ?> <?= $item['unit'] ?>
                    </div>
                    <span style="font-weight:600;">Rs. <?= number_format($item['price'] * $item['quantity'],2) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-item"><span>Subtotal</span><span>Rs. <?= number_format($subtotal,2) ?></span></div>
            <div class="summary-item">
                    <span>Delivery <small style="color:#888; font-size:11px;">(<?= $zoneName ?>)</small></span>
                    <span id="deliveryFeeDisplay"><?= $delivery===0 ? 'Free 🎉' : 'Rs. '.number_format($delivery,2) ?></span>
                </div>
                <div style="font-size:11px; color:#888; text-align:right; margin-top:-8px; margin-bottom:8px;" id="deliveryFeeNote">
                    <?= $delivery > 0 ? 'Free delivery on orders over Rs. '.number_format($freeThresh,0) : '' ?>
                </div>
            <div class="summary-item summary-total" style="border-top:2px solid #eee; padding-top:10px; margin-top:8px;">
                <span>Total</span><span>Rs. <?= number_format($total,2) ?></span>
            </div>
            <button type="submit" class="place-order">✓ Place Order</button>
        </div>
    </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

<script>
const expressZ  = ['Colombo','Gampaha','Kalutara','Kandy','Galle','Matara'];
const standardZ = ['Nuwara Eliya','Matale','Badulla','Ratnapura','Kegalle','Hambantota','Kurunegala'];
const extendedZ = ['Jaffna','Kilinochchi','Mannar','Vavuniya','Trincomalee','Batticaloa','Ampara','Puttalam','Anuradhapura','Polonnaruwa','Monaragala'];

function updateDeliveryFee(district) {
    let zone, fee, freeFrom;
    if (expressZ.includes(district))       { zone='Express Zone';  fee=150; freeFrom=1000; }
    else if (standardZ.includes(district)) { zone='Standard Zone'; fee=200; freeFrom=1000; }
    else if (extendedZ.includes(district)) { zone='Extended Zone'; fee=350; freeFrom=1500; }
    else                                    { zone='Standard Zone'; fee=200; freeFrom=1000; }

    const subtotal = <?= $subtotal ?>;
    const finalFee = subtotal >= freeFrom ? 0 : fee;

    document.getElementById('zoneLabel').textContent = '(' + zone + ')';
    document.getElementById('deliveryFeeDisplay').textContent = finalFee === 0 ? 'Free 🎉' : 'Rs. ' + finalFee.toFixed(2);
    document.getElementById('deliveryFeeNote').textContent = finalFee > 0 ? 'Free delivery on orders over Rs. ' + freeFrom.toLocaleString() : '';
    document.getElementById('totalDisplay').textContent = 'Rs. ' + (subtotal + finalFee).toFixed(2);
}
// Run on page load if district already selected
window.addEventListener('DOMContentLoaded', () => {
    const d = document.getElementById('deliveryDistrict');
    if (d && d.value) updateDeliveryFee(d.value);
});
function showCardForm(val) {
    document.getElementById('cardDetails').style.display = val === 'card' ? 'block' : 'none';
    document.getElementById('bankDetails').style.display = val === 'bank_transfer' ? 'block' : 'none';
}
function previewReceipt(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('receiptPreview').style.display = 'block';
            document.getElementById('uploadZone').style.borderColor = '#27ae60';
            document.getElementById('uploadZone').style.background  = '#e8f5e9';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function selectPayment(val, box) {
    document.querySelectorAll('.payment-box').forEach(b => b.style.background = '');
    box.style.background = '#f0f7f0';
    const map = {cash_on_delivery:'cash', bank_transfer:'bank', card:'card'};
    document.getElementById('pay_' + map[val]).checked = true;
    showCardForm(val);
}
function formatCardNumber(input) {
    let v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.replace(/(.{4})/g,'$1 ').trim();
}
function formatExpiry(input) {
    let v = input.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2);
    input.value = v;
}
function toggleCvv() {
    const inp = document.getElementById('cardCvv');
    const eye = document.getElementById('cvvEye');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    eye.className = inp.type === 'text' ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
}
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        const payment = document.querySelector('input[name="payment"]:checked')?.value;
        // Validate bank proof upload
        if (payment === 'bank_transfer') {
            const proofInput = document.getElementById('bankProofInput');
            const errDiv = document.getElementById('bankProofError');
            if (!proofInput.files || proofInput.files.length === 0) {
                errDiv.textContent = 'Please upload your bank transfer receipt before placing the order.';
                errDiv.style.display = 'block';
                document.getElementById('bankDetails').scrollIntoView({behavior:'smooth'});
                e.preventDefault(); return;
            }
            errDiv.style.display = 'none';
            return; 
        }
        if (payment !== 'card') return;
        const name   = document.getElementById('cardName').value.trim();
        const number = document.getElementById('cardNumber').value.replace(/\s/g,'');
        const expiry = document.getElementById('cardExpiry').value.trim();
        const cvv    = document.getElementById('cardCvv').value.trim();
        const errDiv = document.getElementById('cardError');
        if (!name)              { errDiv.textContent='Please enter the cardholder name.';  errDiv.style.display='block'; e.preventDefault(); return; }
        if (number.length < 13) { errDiv.textContent='Please enter a valid card number.';  errDiv.style.display='block'; e.preventDefault(); return; }
        if (!/^\d{2}\/\d{2}$/.test(expiry)) { errDiv.textContent='Enter expiry as MM/YY.'; errDiv.style.display='block'; e.preventDefault(); return; }
        if (cvv.length < 3)     { errDiv.textContent='CVV must be 3 digits.';              errDiv.style.display='block'; e.preventDefault(); return; }
        ['card_name','card_number','card_expiry','card_cvv'].forEach(n => { const ex=document.querySelector('input[name="'+n+'"]'); if(ex) ex.remove(); });
        const h=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);};
        h('card_name',name); h('card_number',number); h('card_expiry',expiry); h('card_cvv',cvv);
        errDiv.style.display='none';
    });
});
</script>

</body>
</html>
