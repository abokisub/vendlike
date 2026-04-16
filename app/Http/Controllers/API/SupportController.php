<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\MailController;
use Carbon\Carbon;

class SupportController extends Controller
{
    /**
     * VendLike AI Chat Interface - TRANSACTIONAL MODE
     * AI can execute purchases and perform actions on behalf of users
     */
    public function chatVendLike(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized or Session Expired'], 401);
        }

        $messageText = $request->message;
        $message = strtolower($messageText);

        // 1. Get or Create Ticket
        $ticket = $this->getOrCreateTicket($user->id);

        // 2. Check if chat is locked (ticket raised to human support)
        if ($ticket->chat_locked || $ticket->status === 'AWAITING_AGENT' || $ticket->current_handler === 'agent') {
            return response()->json([
                'status' => 'locked',
                'success' => false,
                'message' => 'Your ticket is being handled by our support team. Please wait for their response.',
                'ticket_id' => $ticket->id,
                'chat_locked' => true,
                'awaiting_agent' => true
            ]);
        }

        // 3. Save User Message
        $this->saveMessage($ticket->id, 'user', $user->id, $messageText);

        // 4. Check for human handoff keywords
        if ($this->detectHumanHandoffKeywords($message)) {
            return $this->handleHumanHandoff($user, $ticket, $messageText);
        }

        // 5. Process as TRANSACTIONAL AI (can execute purchases)
        $botResponse = $this->processTransactionalAI($message, $messageText, $user, $ticket->id, $request);

        // 6. Save Bot Response
        $this->saveMessage($ticket->id, 'bot', null, $botResponse['message']);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => $botResponse['message'],
            'conversation_id' => $ticket->id,
            'handler' => 'ai',
            'chat_locked' => false,
            'actions' => $botResponse['actions'] ?? [],
            'transaction_executed' => $botResponse['transaction_executed'] ?? false,
            'transaction_data' => $botResponse['transaction_data'] ?? null
        ]);
    }
    public function createTicket(Request $request)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        // Prevent Duplicate Open Tickets
        $existing = DB::table('support_tickets')->where('user_id', $user->id)->where('status', '!=', 'closed')->first();
        if ($existing) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'ticket' => $existing,
                'message' => 'You already have an active support session.'
            ]);
        }

        $year = date('Y');
        $count = DB::table('support_tickets')->whereYear('created_at', $year)->count() + 1;
        $ticketCode = 'SUP-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        $id = DB::table('support_tickets')->insertGetId([
            'ticket_code' => $ticketCode,
            'user_id' => $user->id,
            'category' => $request->category ?? 'GENERAL',
            'related_txn_ref' => $request->related_txn_ref,
            'subject' => $request->subject ?? 'Direct Support Request',
            'status' => 'open',
            'priority' => $request->priority ?? 'medium',
            'type' => 'human',
            'current_handler' => 'agent',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        if ($request->message) {
            $this->saveMessage($id, 'user', $user->id, $request->message);
        }

        // Notify admins of direct request
        $this->notifyAdminOfEscalation($user, $id);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'ticket' => DB::table('support_tickets')->where('id', $id)->first()
        ]);
    }

    /**
     * Detect keywords that trigger human handoff
     */
    private function detectHumanHandoffKeywords($message)
    {
        $keywords = [
            'human',
            'human agent',
            'customer support',
            'support agent',
            'agent',
            'chat with agent',
            'talk to someone',
            'real person',
            'complaint',
            'report',
            'ticket',
            'help me',
            'this is wrong',
            'scam',
            'fraud',
            'my money',
            'missing money',
            'refund',
            'reversal',
            'not received',
            'didn\'t get',
            'account issue',
            'transaction failed',
            'money deducted',
            'speak to someone',
            'talk to agent',
            'customer care',
            'admin'
        ];

        $messageLower = strtolower($message);

        foreach ($keywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle human handoff - create ticket and lock chat
     */
    private function handleHumanHandoff($user, $ticket, $lastMessage)
    {
        // 1. Mark ticket as needing human support
        DB::table('support_tickets')
            ->where('id', $ticket->id)
            ->update([
                'status' => 'AWAITING_AGENT',
                'chat_locked' => true,
                'current_handler' => 'agent',
                'escalated_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

        // 2. Send acknowledgment message
        $ackMessage = "Your support ticket has been created. Our team will respond shortly.";

        $this->saveMessage($ticket->id, 'bot', null, $ackMessage, true);

        // 3. Notify admins
        $this->notifyAdminOfEscalation($user, $ticket->id);

        // 4. Return with chat_locked flag
        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => $ackMessage,
            'ticket_id' => $ticket->id,
            'chat_locked' => true,
            'awaiting_agent' => true,
            'handler' => 'agent'
        ]);
    }

    /**
     * Process Transactional AI - Can execute purchases and actions
     * Detects purchase intent and executes transactions
     */
    private function processTransactionalAI($message, $originalMessage, $user, $ticketId, $request)
    {
        // Extract phone numbers and amounts from message
        $phonePattern = '/\b(0[7-9][0-1]\d{8})\b/';
        $amountPattern = '/\b(\d{2,6})\b/';
        
        preg_match($phonePattern, $originalMessage, $phoneMatches);
        preg_match_all($amountPattern, $originalMessage, $amountMatches);
        
        $phone = $phoneMatches[1] ?? null;
        $amount = null;
        
        // Get the most reasonable amount (between 50 and 50000)
        if (!empty($amountMatches[1])) {
            foreach ($amountMatches[1] as $possibleAmount) {
                $amt = (int)$possibleAmount;
                if ($amt >= 50 && $amt <= 50000) {
                    $amount = $amt;
                    break;
                }
            }
        }

        // 1. AIRTIME PURCHASE INTENT
        if (preg_match('/(buy|purchase|get|send|recharge|load).*airtime/i', $message) || 
            preg_match('/airtime.*(buy|purchase|get|send|for)/i', $message)) {
            
            if (!$phone || !$amount) {
                return [
                    'message' => "📱 I can buy airtime for you!\n\nPlease provide:\n• Phone number (e.g., 08012345678)\n• Amount (e.g., ₦100)\n\nExample: \"Buy ₦500 airtime for 08012345678\"",
                    'actions' => [
                        ['label' => 'Example', 'action' => 'example_airtime']
                    ]
                ];
            }
            
            // Execute airtime purchase
            try {
                $purchaseController = new \App\Http\Controllers\Purchase\AirtimePurchase();
                $purchaseRequest = new Request([
                    'phone' => $phone,
                    'amount' => $amount,
                    'network' => 'auto' // Auto-detect from prefix
                ]);
                $purchaseRequest->setUserResolver(function () use ($user) {
                    return $user;
                });
                
                $result = $purchaseController->Buy($purchaseRequest);
                $resultData = $result->getData();
                
                if ($resultData->status === 'success') {
                    return [
                        'message' => "✅ Airtime purchase successful!\n\n📱 Phone: {$phone}\n💰 Amount: ₦" . number_format($amount, 2) . "\n🎯 Status: Delivered\n\nAnything else I can help with?",
                        'transaction_executed' => true,
                        'transaction_data' => [
                            'type' => 'airtime',
                            'phone' => $phone,
                            'amount' => $amount,
                            'reference' => $resultData->reference ?? null
                        ],
                        'actions' => [
                            ['label' => 'Buy More Airtime', 'action' => 'buy_airtime'],
                            ['label' => 'Buy Data', 'action' => 'buy_data']
                        ]
                    ];
                } else {
                    return [
                        'message' => "❌ Airtime purchase failed: " . ($resultData->message ?? 'Unknown error') . "\n\nPlease check your wallet balance or try again.",
                        'actions' => [
                            ['label' => 'Check Balance', 'action' => 'check_balance'],
                            ['label' => 'Contact Support', 'action' => 'speak_human']
                        ]
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('AI Airtime Purchase Failed', ['error' => $e->getMessage(), 'user' => $user->id]);
                return [
                    'message' => "❌ Sorry, I couldn't complete the airtime purchase. Error: " . $e->getMessage() . "\n\nPlease try again or contact support.",
                    'actions' => [
                        ['label' => 'Try Again', 'action' => 'buy_airtime'],
                        ['label' => 'Contact Support', 'action' => 'speak_human']
                    ]
                ];
            }
        }

        // 2. DATA PURCHASE INTENT
        if (preg_match('/(buy|purchase|get|send).*data/i', $message) || 
            preg_match('/data.*(buy|purchase|get|send|for)/i', $message)) {
            
            if (!$phone) {
                return [
                    'message' => "📶 I can buy data for you!\n\nPlease provide:\n• Phone number (e.g., 08012345678)\n• Data plan (e.g., 1GB, 2GB, 5GB)\n\nExample: \"Buy 2GB data for 08012345678\"",
                    'actions' => [
                        ['label' => 'View Data Plans', 'action' => 'view_data_plans']
                    ]
                ];
            }
            
            // Extract data amount (1GB, 2GB, etc.)
            $dataPattern = '/(\d+)\s*(gb|mb)/i';
            preg_match($dataPattern, $originalMessage, $dataMatches);
            
            if (empty($dataMatches)) {
                return [
                    'message' => "📶 Please specify the data plan you want.\n\nAvailable plans:\n• 1GB - ₦250\n• 2GB - ₦500\n• 5GB - ₦1,200\n• 10GB - ₦2,400\n\nExample: \"Buy 2GB data for {$phone}\"",
                    'actions' => [
                        ['label' => 'View All Plans', 'action' => 'view_data_plans']
                    ]
                ];
            }
            
            $dataSize = $dataMatches[1] . $dataMatches[2];
            
            return [
                'message' => "📶 Data purchase feature coming soon!\n\nFor now, please use:\nServices > Data > Select Plan\n\nOr I can guide you through the process?",
                'actions' => [
                    ['label' => 'Guide Me', 'action' => 'faq_data'],
                    ['label' => 'Buy Airtime Instead', 'action' => 'buy_airtime']
                ]
            ];
        }

        // 3. BALANCE CHECK INTENT
        if (preg_match('/(check|show|what|my).*(balance|wallet)/i', $message) || 
            preg_match('/(balance|wallet).*(check|show|what)/i', $message)) {
            
            $balance = $user->bal ?? 0;
            return [
                'message' => "💰 Your Wallet Balance:\n\n₦" . number_format($balance, 2) . "\n\nWhat would you like to do?",
                'actions' => [
                    ['label' => 'Fund Wallet', 'action' => 'fund_wallet'],
                    ['label' => 'Buy Airtime', 'action' => 'buy_airtime'],
                    ['label' => 'Buy Data', 'action' => 'buy_data']
                ]
            ];
        }

        // 4. TRANSACTION HISTORY INTENT
        if (preg_match('/(show|view|check|my).*(transaction|history|purchases)/i', $message)) {
            $recentTransactions = DB::table('message')
                ->where('username', $user->username)
                ->orderBy('habukhan_date', 'desc')
                ->limit(5)
                ->get(['message', 'amount', 'habukhan_date', 'plan_status']);
            
            if ($recentTransactions->isEmpty()) {
                return [
                    'message' => "📋 No recent transactions found.\n\nStart using Vendlike services to see your transaction history here!",
                    'actions' => [
                        ['label' => 'Buy Airtime', 'action' => 'buy_airtime'],
                        ['label' => 'Buy Data', 'action' => 'buy_data']
                    ]
                ];
            }
            
            $historyText = "📋 Your Recent Transactions:\n\n";
            foreach ($recentTransactions as $txn) {
                $status = $txn->plan_status == 1 ? '✅' : ($txn->plan_status == 2 ? '❌' : '⏳');
                $historyText .= "{$status} " . substr($txn->message, 0, 50) . "...\n";
                $historyText .= "   ₦" . number_format($txn->amount, 2) . " - " . date('M d, Y', strtotime($txn->habukhan_date)) . "\n\n";
            }
            
            return [
                'message' => $historyText . "Need anything else?",
                'actions' => [
                    ['label' => 'Buy Airtime', 'action' => 'buy_airtime'],
                    ['label' => 'Check Balance', 'action' => 'check_balance']
                ]
            ];
        }

        // If no transactional intent detected, fall back to FAQ mode
        return $this->processFAQResponse($message, $user, $ticketId);
    }

    /**
     * Process FAQ Response - Enhanced AI with comprehensive Vendlike knowledge
     * Trained on all services: Airtime, Data, Bills, A2Cash, Gift Cards, Marketplace, Transfers
     */
    private function processFAQResponse($message, $user, $ticketId)
    {
        // 1. Greetings
        if (preg_match('/^(hi|hello|hey|greetings|good morning|good afternoon|good evening|welcome)/i', $message)) {
            return [
                'message' => "Hi {$user->username} 👋 Welcome to Vendlike!\n\nI'm your AI assistant. I can help you:\n\n✅ **Buy airtime & data** (just tell me!)\n✅ Check your balance\n✅ View transaction history\n✅ Answer questions about services\n✅ Guide you through features\n\n**Try saying:**\n• \"Buy ₦500 airtime for 08012345678\"\n• \"Check my balance\"\n• \"Show my transactions\"\n\nWhat can I do for you?",
                'actions' => [
                    ['label' => 'Check Balance', 'action' => 'check_balance'],
                    ['label' => 'Buy Airtime', 'action' => 'buy_airtime'],
                    ['label' => 'Service Fees', 'action' => 'faq_fees'],
                    ['label' => 'Contact Support', 'action' => 'speak_human']
                ]
            ];
        }

        // 2. Wallet Funding FAQs
        if (preg_match('/(fund|deposit|add money|top up|payment)/i', $message)) {
            return [
                'message' => "💰 **How to Add Cash to Your Wallet:**\n\n1. Go to Wallet section\n2. Click 'Add Cash'\n3. Choose payment method (Card/Bank Transfer)\n4. Enter amount and confirm\n5. Funds reflect instantly\n\n**Minimum:** ₦100\n**Maximum:** Based on your KYC level",
                'actions' => [
                    ['label' => 'KYC Limits', 'action' => 'faq_kyc'],
                    ['label' => 'Payment Issues?', 'action' => 'speak_human']
                ]
            ];
        }

        // 3. Airtime Purchase FAQs
        if (preg_match('/(airtime|recharge|credit)/i', $message)) {
            return [
                'message' => "📱 **How to Buy Airtime:**\n\n1. Go to Services > Airtime\n2. Select network (MTN, Glo, Airtel, 9mobile)\n3. Enter phone number\n4. Enter amount\n5. Confirm purchase\n\n**Fee:** ₦0 - ₦10 depending on amount\n**Instant delivery**",
                'actions' => [
                    ['label' => 'Data Bundles', 'action' => 'faq_data'],
                    ['label' => 'Service Fees', 'action' => 'faq_fees']
                ]
            ];
        }

        // 4. Data Bundle FAQs
        if (preg_match('/(data|bundle|internet|mb|gb)/i', $message)) {
            return [
                'message' => "📶 **How to Buy Data:**\n\n1. Go to Services > Data\n2. Select network\n3. Choose data plan\n4. Enter phone number\n5. Confirm purchase\n\n**All networks supported**\n**Instant activation**",
                'actions' => [
                    ['label' => 'Airtime', 'action' => 'faq_airtime'],
                    ['label' => 'Bills Payment', 'action' => 'faq_bills']
                ]
            ];
        }

        // 5. A2Cash FAQs
        if (preg_match('/(a2cash|airtime to cash|convert|sell airtime)/i', $message)) {
            return [
                'message' => "💸 **How A2Cash Works:**\n\n1. Go to A2Cash section\n2. Select network\n3. Enter amount to convert\n4. Transfer airtime to our number\n5. Cash credited to wallet\n\n**Rate:** 75-85% of airtime value\n**Processing:** 5-15 minutes",
                'actions' => [
                    ['label' => 'Current Rates', 'action' => 'faq_rates'],
                    ['label' => 'Conversion Issues?', 'action' => 'speak_human']
                ]
            ];
        }

        // 6. Transaction Status FAQs
        if (preg_match('/(pending|failed|status|track|where is)/i', $message)) {
            return [
                'message' => "⏳ **Transaction Status Guide:**\n\n**Pending:** Processing (usually 1-5 mins)\n**Success:** Completed\n**Failed:** Refunded to wallet\n\n**If pending >30 mins:** Contact support\n**If failed but not refunded:** Contact support\n\nI can guide you on how things work, but for specific transaction issues, our support team can help.",
                'actions' => [
                    ['label' => 'Contact Support', 'action' => 'speak_human'],
                    ['label' => 'Main Menu', 'action' => 'help']
                ]
            ];
        }

        // 7. KYC Level FAQs
        if (preg_match('/(kyc|level|tier|limit|upgrade|verify)/i', $message)) {
            return [
                'message' => "🔐 **KYC Levels & Limits:**\n\n**Level 1 (Basic):**\n• Daily: ₦50,000\n• Monthly: ₦200,000\n\n**Level 2 (Verified):**\n• Daily: ₦200,000\n• Monthly: ₦1,000,000\n\n**Level 3 (Premium):**\n• Daily: ₦1,000,000\n• Monthly: ₦5,000,000\n\n**To upgrade:** Go to Profile > KYC Verification",
                'actions' => [
                    ['label' => 'How to Verify', 'action' => 'faq_verify'],
                    ['label' => 'Verification Issues?', 'action' => 'speak_human']
                ]
            ];
        }

        // 8. Fees FAQs
        if (preg_match('/(fee|charge|cost|price)/i', $message)) {
            return [
                'message' => "💵 **Service Fees:**\n\n• Airtime: ₦0 - ₦10\n• Data: ₦0 - ₦20\n• Bills: ₦50 - ₦100\n• Bank Transfer: ₦25 - ₦50\n• A2Cash: 15-25% commission\n\n**No hidden charges**\n**Fees shown before confirmation**",
                'actions' => [
                    ['label' => 'Service Guide', 'action' => 'faq_services'],
                    ['label' => 'Main Menu', 'action' => 'help']
                ]
            ];
        }

        // 9. Balance/Wallet FAQs
        if (preg_match('/(balance|wallet|account|money)/i', $message)) {
            return [
                'message' => "I can guide you on how the wallet works:\n\n• **Check Balance:** Go to Wallet tab\n• **Fund Wallet:** Use Card or Bank Transfer\n• **Withdraw:** Bank Transfer (₦100 minimum)\n• **Transaction History:** View in Wallet section\n\nFor account-specific balance issues, our support team can help.",
                'actions' => [
                    ['label' => 'How to Fund', 'action' => 'faq_funding'],
                    ['label' => 'Balance Issue?', 'action' => 'speak_human']
                ]
            ];
        }

        // 10. Transfer FAQs
        if (preg_match('/(transfer|send money|move money|wire)/i', $message)) {
            return [
                'message' => "💸 **How to Transfer Money:**\n\n**To Other Banks:**\n1. Go to Send Money\n2. Select 'Bank Transfer'\n3. Choose bank and enter account number\n4. Enter amount and confirm\n\n**Internal Transfer (Free):**\n1. Go to Send Money\n2. Select 'Wallet Transfer'\n3. Enter recipient's username\n4. Confirm\n\n**Fees:** ₦25 - ₦50 internally/externally",
                'actions' => [
                    ['label' => 'Internal Transfer', 'action' => 'faq_internal'],
                    ['label' => 'Transfer Limits', 'action' => 'faq_kyc']
                ]
            ];
        }

        // 11. Acknowledgments (Follow-ups)
        if (preg_match('/^(yes|ok|okay|fine|cool|thanks|thank you|awesome|perfect)/i', $message)) {
            return [
                'message' => "You're welcome! 😊 Is there anything else you'd like to know about our services, fees, or how to use the app?",
                'actions' => [
                    ['label' => 'Service Fees', 'action' => 'faq_fees'],
                    ['label' => 'Transaction Status', 'action' => 'faq_status'],
                    ['label' => 'Main Menu', 'action' => 'help']
                ]
            ];
        }

        // 12. Default: Offer FAQ categories
        return [
            'message' => "I understand you're looking for information. I can guide you on how the system works, fees, and common issues.\n\nFor account-specific issues, our support team is available. What would you like to know about?",
            'actions' => [
                ['label' => 'How to Fund Wallet', 'action' => 'faq_funding'],
                ['label' => 'Service Fees', 'action' => 'faq_fees'],
                ['label' => 'KYC Levels', 'action' => 'faq_kyc'],
                ['label' => 'Transaction Status', 'action' => 'faq_status'],
                ['label' => 'Chat with Support', 'action' => 'speak_human']
            ]
        ];
    }

    private function escalateToAgent($user, $ticketId, $customMessage = null)
    {
        DB::table('support_tickets')->where('id', $ticketId)->update([
            'type' => 'human',
            'current_handler' => 'agent',
            'status' => 'open',
            'updated_at' => now()
        ]);

        $this->notifyAdminOfEscalation($user, $ticketId);

        return [
            'message' => $customMessage ?? "I’ve connected you to a human support agent 👨💼\nPlease hold on while they review your request. I'll stay here if you need anything else later!",
            'escalate_to_agent' => true
        ];
    }

    private function notifyAdminOfEscalation($user, $ticketId)
    {
        try {
            $general = DB::table('general')->first();
            $admins = DB::table('user')->where('type', 'ADMIN')->get();
            foreach ($admins as $admin) {
                // Send Email
                $email_data = [
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'username' => $user->username,
                    'title' => 'SUPPORT ESCALATION',
                    'sender_mail' => $general->app_email ?? 'support@vendlike.com',
                    'user_email' => $user->email,
                    'app_name' => $general->app_name ?? 'App',
                    'website' => '',
                    'date' => now(),
                    'transid' => $ticketId,
                    'app_phone' => $general->app_phone ?? ''
                ];
                // Assuming MailController::send_mail exists
                MailController::send_mail($email_data, 'email.support_escalation');

                // Dashboard Notification (DB request table or similar)
                DB::table('request')->insert([
                    'username' => $user->username,
                    'message' => "User {$user->username} requested human agent (Ticket #$ticketId)",
                    'date' => now(),
                    'transid' => "TKT-$ticketId",
                    'status' => 0,
                    'title' => 'SUPPORT TICKET'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Escalation Notification Failed: " . $e->getMessage());
        }
    }

    /**
     * Messaging logic for User <-> Human Agent (Post-Escalation)
     */
    public function sendUserMessage(Request $request, $ticketId)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $attachmentUrl = null;
        $attachmentType = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('support_uploads', 'public');
            $attachmentUrl = asset('storage/' . $path);
            $attachmentType = 'image';
        }

        $this->saveMessage($ticketId, 'user', $user->id, $request->message, false, $attachmentUrl, $attachmentType);

        // Update ticket last activity
        DB::table('support_tickets')->where('id', $ticketId)->update([
            'last_message' => $request->message,
            'last_message_at' => now(),
            'status' => 'open' // Reopen if closed
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getChatMessages(Request $request, $ticketId)
    {
        $messages = DB::table('support_messages')
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();

        $ticket = DB::table('support_tickets')->where('id', $ticketId)->first();

        return response()->json([
            'status' => 'success',
            'messages' => $messages,
            'ticket_status' => $ticket->status ?? 'unknown',
            'current_handler' => $ticket->current_handler ?? 'ai',
            'typing_status' => $ticket->typing_status,
            'typing_agent_name' => $ticket->typing_agent_name,
            'typing_started_at' => $ticket->typing_started_at
        ]);
    }

    public function typing(Request $request)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['status' => 'error'], 401);

        $ticketId = $request->ticket_id;
        $status = $request->status; // 'start', 'stop'

        // If user, get their active ticket if not provided
        if ($user->type !== 'ADMIN' && !$ticketId) {
            $ticket = $this->getOrCreateTicket($user->id);
            $ticketId = $ticket->id;
        }

        $typingStatus = null;
        $agentName = null;

        if ($status === 'start') {
            $typingStatus = ($user->type === 'ADMIN') ? 'agent' : 'user';
            $agentName = ($user->type === 'ADMIN') ? $user->username : null;
        }

        DB::table('support_tickets')->where('id', $ticketId)->update([
            'typing_status' => $typingStatus,
            'typing_agent_name' => $agentName,
            'typing_started_at' => ($status === 'start') ? now() : null
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getTickets(Request $request)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $tickets = DB::table('support_tickets')
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'tickets' => $tickets
        ]);
    }

    // Helper: Internal save
    private function saveMessage($ticketId, $senderType, $senderId, $message, $isSystem = false, $attachmentUrl = null, $attachmentType = null)
    {
        DB::table('support_messages')->insert([
            'ticket_id' => $ticketId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
            'system_message' => $isSystem,
            'created_at' => now(),
            'updated_at' => now(),
            'attachment_url' => $attachmentUrl,
            'attachment_type' => $attachmentType
        ]);

        // Professional Update: Ensure ticket itself reflects recent activity
        DB::table('support_tickets')->where('id', $ticketId)->update(['updated_at' => now()]);
    }

    // Helper: Ticket persistence
    private function getOrCreateTicket($userId)
    {
        // One user = One active ticket logic
        $ticket = DB::table('support_tickets')
            ->where('user_id', $userId)
            ->where('status', '!=', 'closed')
            ->first();

        if ($ticket) {
            // PROFESSIONAL ADDITION: Auto-reset "stuck" agent sessions if inactive for 12 hours
            // This ensures users aren't left in limbo if an agent never joins or forgets to close.
            if ($ticket->current_handler == 'agent' && Carbon::parse($ticket->updated_at)->diffInHours(now()) >= 12) {
                DB::table('support_tickets')->where('id', $ticket->id)->update([
                    'current_handler' => 'ai',
                    'updated_at' => now()
                ]);

                // Log a system notification in the chat
                $this->saveMessage($ticket->id, 'bot', null, "Agent was unavailable, so VendLike AI has resumed to assist you! 👋", true);

                // Refresh ticket object
                $ticket = DB::table('support_tickets')->where('id', $ticket->id)->first();
            }
            return $ticket;
        }

        $year = date('Y');
        $count = DB::table('support_tickets')->whereYear('created_at', $year)->count() + 1;
        $ticketCode = 'SUP-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        $id = DB::table('support_tickets')->insertGetId([
            'ticket_code' => $ticketCode,
            'user_id' => $userId,
            'subject' => 'AI Support Chat',
            'status' => 'open',
            'type' => 'ai',
            'current_handler' => 'ai',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return DB::table('support_tickets')->where('id', $id)->first();
    }

    /**
     * ADMIN FUNCTIONS
     */

    public function adminGetOpenTickets(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->type !== 'ADMIN')
            return response()->json(['status' => 'error', 'message' => 'Admin only'], 403);

        $tickets = DB::table('support_tickets')
            ->join('user', 'support_tickets.user_id', '=', 'user.id')
            ->select('support_tickets.*', 'user.username', 'user.name as user_fullname')
            ->where('support_tickets.status', '!=', 'closed')
            ->orderBy('support_tickets.updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'tickets' => $tickets
        ]);
    }

    public function adminReply(Request $request, $ticketId)
    {
        $user = $request->user();
        if (!$user || $user->type !== 'ADMIN')
            return response()->json(['status' => 'error', 'message' => 'Admin only'], 403);

        $attachmentUrl = null;
        $attachmentType = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('support_uploads', 'public');
            $attachmentUrl = asset('storage/' . $path);
            $attachmentType = 'image';
        }

        $this->saveMessage($ticketId, 'agent', $user->id, $request->message, false, $attachmentUrl, $attachmentType);

        DB::table('support_tickets')->where('id', $ticketId)->update([
            'last_message' => $request->message,
            'last_message_at' => now(),
            'status' => 'pending', // Waiting for user
            'current_handler' => 'agent' // Agent takes full control
        ]);

        return response()->json(['status' => 'success']);
    }

    public function adminCloseTicket(Request $request, $ticketId)
    {
        $user = $request->user();
        if (!$user || $user->type !== 'ADMIN')
            return response()->json(['status' => 'error', 'message' => 'Admin only'], 403);

        $ticket = DB::table('support_tickets')->where('id', $ticketId)->first();
        if (!$ticket)
            return response()->json(['status' => 'error', 'message' => 'Ticket not found'], 404);

        // Notify User via System Message
        $this->saveMessage($ticketId, 'agent', $user->id, "👋 Support session ended. Agent {$user->name} has closed this ticket. VendLike AI is back to help you!", true);

        DB::table('support_tickets')->where('id', $ticketId)->update([
            'status' => 'closed',
            'current_handler' => 'ai',
            'closed_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['status' => 'success']);
    }

    public function closeTicket(Request $request, $ticketId)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $ticket = DB::table('support_tickets')->where('id', $ticketId)->first();
        if (!$ticket)
            return response()->json(['status' => 'error', 'message' => 'Ticket not found'], 404);

        // Notify User via System Message
        $this->saveMessage($ticketId, 'agent', $user->id, "👋 Support session ended. Agent {$user->name} has closed this ticket. VendLike AI is back to help you!", true);

        DB::table('support_tickets')->where('id', $ticketId)->update([
            'status' => 'closed',
            'current_handler' => 'ai',
            'closed_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['status' => 'success']);
    }
}