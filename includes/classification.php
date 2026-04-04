<?php

class ClassificationService {
    private static $rules = [
        ['category' => 'OTP', 'keywords' => ['otp', 'one time password', 'verification code', 'verify', 'code is']],
        ['category' => 'Bank_Transactions', 'keywords' => ['debited', 'credited', 'acct', 'a/c', 'account', 'txn', 'transfer']],
        ['category' => 'Credit_Card', 'keywords' => ['credit card', 'statement', 'due', 'outstanding', 'minimum due', 'cc bill']],
        ['category' => 'Spam_Promotions', 'keywords' => ['offer', 'discount', 'sale', 'free', 'win', 'prize', 'claim', 'bonus', 'promo', 'cashback']],
        ['category' => 'Loan_EMI', 'keywords' => ['loan', 'emi', 'installment', 'payment due', 'overdue']],
        ['category' => 'Insurance', 'keywords' => ['insurance', 'policy', 'premium', 'renewal', 'claim']],
        ['category' => 'Investment_Trading', 'keywords' => ['demat', 'trading', 'portfolio', 'dividend', 'nfo', 'sip', 'mutual fund']],
        ['category' => 'Utility_Bills', 'keywords' => ['bill', 'electricity', 'water', 'gas', 'broadband', 'payment received']],
        ['category' => 'Recharge_Data', 'keywords' => ['recharge', 'data pack', 'validity', 'plan', 'prepaid', 'postpaid', 'topup']],
        ['category' => 'Service_Alerts', 'keywords' => ['ticket', 'resolved', 'service request', 'technician']],
        ['category' => 'Order_Confirmation', 'keywords' => ['order', 'confirmed', 'shipped', 'placed', 'dispatch']],
        ['category' => 'Delivery_Updates', 'keywords' => ['out for delivery', 'delivered', 'arriving', 'courier']],
        ['category' => 'Refunds_Returns', 'keywords' => ['refund', 'return', 'processed', 'initiated']],
        ['category' => 'Booking_Travel', 'keywords' => ['booking', 'ticket', 'flight', 'train', 'bus', 'hotel', 'pnr']],
        ['category' => 'Travel_Alerts', 'keywords' => ['delayed', 'rescheduled', 'boarding', 'terminal', 'gate']],
        ['category' => 'Work_Alerts', 'keywords' => ['meeting', 'deadline', 'reminder', 'shift', 'roster']],
        ['category' => 'System_Notifications', 'keywords' => ['system update', 'maintenance', 'downtime']],
        ['category' => 'Government', 'keywords' => ['aadhar', 'pan', 'voter', 'driving license', 'rto', 'challan']],
        ['category' => 'Tax_Compliance', 'keywords' => ['itr', 'tax', 'tds', 'gst', 'assessment']],
        ['category' => 'Personal', 'keywords' => ['happy birthday', 'anniversary', 'congratulations']],
        ['category' => 'Event_Invitations', 'keywords' => ['invite', 'wedding', 'reception', 'party', 'join us']],
        ['category' => 'Fraud_Alerts', 'keywords' => ['suspicious', 'unauthorized', 'blocked', 'compromised', 'fraud']],
        ['category' => 'Security_Notifications', 'keywords' => ['login', 'password changed', 'device', 'access']]
    ];

    public static function classify($text, $sender) {
        $lowerText = strtolower($text);
        
        foreach (self::$rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (strpos($lowerText, $keyword) !== false) {
                    return $rule['category'];
                }
            }
        }

        return 'UNCATEGORIZED';
    }
}
