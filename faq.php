<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ | DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .faq-hero-banner {
            background: linear-gradient(135deg, #0e1726 0%, #1a3a2a 60%, #009933 100%);
            color: white;
            text-align: center;
            padding: 60px 20px;
        }
        .faq-hero-banner h1 { font-size: 32px; margin-bottom: 10px; color: white; font-weight: 700; }
        .faq-hero-banner p  { opacity: 0.9; font-weight: 300; color: white; }

        .faq-container {
            max-width: 800px;
            margin: -40px auto 40px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .faq-item { border-bottom: 1px solid #E5E7EB; }
        .faq-item:last-child { border-bottom: none; }
        summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            list-style: none;
        }
        summary::after { content: '⌄'; font-size: 20px; color: #666; }
        details[open] summary::after { content: '⌃'; }
        .faq-answer { padding-bottom: 15px; color: #666; font-size: 14px; }

        .support-cta {
            background-color: #F1F8F1;
            max-width: 800px;
            margin: 0 auto 60px;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
        }
        .support-cta h3 { margin-bottom: 10px; font-size: 18px; }
        .support-cta p { color: #666; font-size: 14px; margin-bottom: 20px; }
        .cta-btns { display: flex; justify-content: center; gap: 15px; }
        .btn-email {
            background: #56ad58; color: white;
            padding: 10px 25px; border-radius: 25px; text-decoration: none;
        }
        .btn-call {
            border: 1px solid #56ad58; color: #56ad58;
            padding: 10px 25px; border-radius: 25px; text-decoration: none;
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<section class="faq-hero-banner">
    <h1>Frequently Asked Questions</h1>
    <p>Find answers to common questions about DirectFarm LK</p>
</section>

<div class="faq-container">
    <details class="faq-item" open>
        <summary>How does DirectFarm LK work?</summary>
        <div class="faq-answer">DirectFarm LK connects local farmers directly with consumers, ensuring fresher produce and better prices for everyone involved.</div>
    </details>
    <details class="faq-item">
        <summary>Is there a delivery fee?</summary>
        <div class="faq-answer">Delivery is free on orders above Rs. 1,000. Below that, a fee of Rs. 200 applies depending on your zone.</div>
    </details>
    <details class="faq-item">
        <summary>How do I know the produce is fresh?</summary>
        <div class="faq-answer">We prioritize same-day harvesting and direct delivery from farm to table. All farmers are verified before listing products.</div>
    </details>
    <details class="faq-item">
        <summary>Can I return or exchange products?</summary>
        <div class="faq-answer">Yes, we have a 24-hour return policy for quality-related issues. Contact support with a photo of the product.</div>
    </details>
    <details class="faq-item">
        <summary>What payment methods do you accept?</summary>
        <div class="faq-answer">We accept credit/debit cards, bank transfers, and cash on delivery.</div>
    </details>
    <details class="faq-item">
        <summary>How do I become a seller/farmer on DirectFarm LK?</summary>
        <div class="faq-answer">Click on the 'Register' button and select 'Farmer' to start your onboarding process. An admin will verify your account.</div>
    </details>
    <details class="faq-item">
        <summary>What areas do you deliver to?</summary>
        <div class="faq-answer">We deliver to all 24 districts in Sri Lanka. Delivery times vary by zone — 1 to 5 days depending on your location.</div>
    </details>
    <details class="faq-item">
        <summary>How do I track my order?</summary>
        <div class="faq-answer">Visit our Delivery page, enter your Order ID and email to see live tracking of your order status.</div>
    </details>
    <details class="faq-item">
        <summary>Can I contact the farmer directly?</summary>
        <div class="faq-answer">Yes! On any product page, click "Contact Farmer" to send a message. The farmer will reply through the platform.</div>
    </details>
</div>

<section class="support-cta">
    <h3>Still have questions?</h3>
    <p>Can't find the answer you're looking for? Our customer support team is here to help.</p>
    <div class="cta-btns">
        <a href="mailto:info@directfarmlk.com" class="btn-email">Email Us</a>
        <a href="tel:+94112345678" class="btn-call">Call Us</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
</body>
</html>
