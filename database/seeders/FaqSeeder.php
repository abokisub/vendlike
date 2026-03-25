<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('faqs')->insert([
            [
                'question' => 'What is VendLike?',
                'answer' => 'VendLike is a smart digital services platform that allows you to buy data, recharge airtime, pay bills, trade gift cards, shop the marketplace, and withdraw earnings — all in one secure app.',
                'category' => 'General',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'How do I fund my wallet?',
                'answer' => 'You can fund your wallet via bank transfer, USSD, or credit/debit card. Navigate to the "Fund Wallet" section on your dashboard for more details.',
                'category' => 'Account',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'Is my data secure?',
                'answer' => 'Yes, we use industry-standard encryption and security protocols to ensure your data and transactions are always protected.',
                'category' => 'Security',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'How can I contact support?',
                'answer' => 'You can reach us via our live chat ("VendLike AI"), send an email to our support team, or contact us through WhatsApp. Our support channels are available 24/7.',
                'category' => 'Support',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'What is the "VendLike AI" Chat?',
                'answer' => 'VendLike AI is our smart support assistant designed to provide instant answers to your questions. For complex issues, VendLike AI can also connect you to a human agent.',
                'category' => 'Support',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
