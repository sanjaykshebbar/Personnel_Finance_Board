<?php
// includes/AIParser.php
// Wraps the Anthropic Messages API to turn free text (a pasted note, an SMS
// body, or a whole statement dump) into zero or more structured transactions.

class AIParserException extends Exception {}

class AIParser {
    const API_URL = 'https://api.anthropic.com/v1/messages';
    const MAX_INPUT_CHARS = 100000;
    const MAX_TOKENS = 4096;

    private $apiKey;
    private $model;

    public function __construct() {
        $this->apiKey = getAnthropicApiKey();
        $this->model = getAiModel();

        if (!$this->apiKey) {
            throw new AIParserException('ANTHROPIC_API_KEY is not configured. Set it in your .env file.');
        }
    }

    /**
     * Parse raw text into zero or more structured transactions.
     *
     * @param string $rawText Free text: a pasted note, an SMS body, or extracted statement text.
     * @param string $source  'paste' | 'upload' | 'sms' — only used to tailor the prompt.
     * @param array  $knownCategories Existing category names, used as a strong hint (not enforced).
     * @return array List of transaction arrays: type, date, amount, category, description,
     *               payment_method, target_account (optional), confidence.
     */
    public function parseText($rawText, $source, array $knownCategories = []) {
        $rawText = trim((string)$rawText);
        if ($rawText === '') return [];

        if (strlen($rawText) > self::MAX_INPUT_CHARS) {
            throw new AIParserException(
                'This text is too large to parse in one go (' . strlen($rawText) . ' chars). ' .
                'Please split it into smaller chunks (e.g. one statement cycle at a time) and try again.'
            );
        }

        $systemPrompt = $this->buildSystemPrompt($source, $knownCategories);
        $tool = $this->buildToolSchema();

        $payload = [
            'model' => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $rawText],
            ],
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => 'extract_transactions'],
        ];

        $response = $this->callApi($payload);

        return $this->extractTransactions($response);
    }

    private function buildSystemPrompt($source, array $knownCategories) {
        $today = date('Y-m-d');
        $categoryList = !empty($knownCategories) ? implode(', ', $knownCategories) : 'Other';

        $sourceHint = 'a snippet of text pasted by the user (could be a single note like "500 for lunch" or a multi-line statement dump)';
        if ($source === 'sms') {
            $sourceHint = 'the body of a single bank/wallet SMS notification';
        } elseif ($source === 'upload') {
            $sourceHint = 'text extracted from an uploaded bank statement (CSV or PDF) - it may contain many transaction rows';
        }

        return <<<PROMPT
You extract financial transactions from {$sourceHint}.

Today's date (use as a reference for relative dates like "yesterday"): {$today}
Known expense categories for this user (prefer these; you may propose a short new one only if truly nothing fits): {$categoryList}
Known payment methods include: Cash, UPI, Bank Transfer, Debit Card, and credit card names.

Rules:
- If the text does NOT describe an actual money movement (e.g. it's an OTP, a promotional message, a balance inquiry, a failed-transaction notice, or unrelated chatter), return an empty transactions array. Do not guess.
- "type" is "expense" for money going out (debited/spent/paid) and "income" for money coming in (credited/received).
- "amount" is always a positive number, in the transaction's currency, no currency symbols or commas.
- "date" must be YYYY-MM-DD. If no date is stated, use today's date.
- "category" should be one of the known categories when it clearly fits, otherwise pick the closest sensible one.
- "payment_method" should be inferred from the text (e.g. "Credit Card", "UPI", "Bank Transfer", "Cash", or the specific card/bank name if named).
- "target_account" is optional — only set it when this transaction is clearly a bill payment TO an account (e.g. paying off a credit card from a bank account), naming the account being paid.
- "confidence" is a number from 0 to 1 reflecting how sure you are about the extracted values as a whole.
- A statement dump or multi-line paste may contain multiple transactions — extract all of them.
PROMPT;
    }

    private function buildToolSchema() {
        return [
            'name' => 'extract_transactions',
            'description' => 'Record the financial transactions found in the given text, if any.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'transactions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => ['type' => 'string', 'enum' => ['expense', 'income']],
                                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                                'amount' => ['type' => 'number'],
                                'category' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'payment_method' => ['type' => 'string'],
                                'target_account' => ['type' => 'string'],
                                'confidence' => ['type' => 'number'],
                            ],
                            'required' => ['type', 'date', 'amount', 'category', 'description', 'payment_method', 'confidence'],
                        ],
                    ],
                ],
                'required' => ['transactions'],
            ],
        ];
    }

    private function callApi(array $payload) {
        $ch = curl_init(self::API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new AIParserException('AI request failed: ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);

        if ($httpCode !== 200) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            throw new AIParserException('AI request failed: ' . $msg);
        }

        if (!is_array($decoded)) {
            throw new AIParserException('AI returned an unexpected response.');
        }

        return $decoded;
    }

    private function extractTransactions(array $response) {
        $content = $response['content'] ?? [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'extract_transactions') {
                $transactions = $block['input']['transactions'] ?? [];
                return $this->sanitizeTransactions($transactions);
            }
        }

        return [];
    }

    private function sanitizeTransactions(array $transactions) {
        $out = [];
        foreach ($transactions as $txn) {
            if (!isset($txn['amount']) || !is_numeric($txn['amount']) || (float)$txn['amount'] <= 0) continue;
            $type = ($txn['type'] ?? '') === 'income' ? 'income' : 'expense';

            $out[] = [
                'type' => $type,
                'date' => $this->sanitizeDate($txn['date'] ?? null),
                'amount' => round((float)$txn['amount'], 2),
                'category' => trim((string)($txn['category'] ?? 'Other')) ?: 'Other',
                'description' => trim((string)($txn['description'] ?? '')),
                'payment_method' => trim((string)($txn['payment_method'] ?? 'Cash')) ?: 'Cash',
                'target_account' => trim((string)($txn['target_account'] ?? '')),
                'confidence' => isset($txn['confidence']) ? max(0, min(1, (float)$txn['confidence'])) : 0.5,
            ];
        }
        return $out;
    }

    private function sanitizeDate($date) {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        return date('Y-m-d');
    }
}
?>
