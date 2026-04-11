<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateFaqsBranding extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update Questions - Replace Kobopoint with VendLike
        DB::table('faqs')->where('question', 'like', '%Kobopoint%')->orWhere('question', 'like', '%kobopoint%')->get()->each(function ($faq) {
            $newQuestion = str_ireplace(['Kobopoint', 'kobopoint'], 'VendLike', $faq->question);
            DB::table('faqs')->where('id', $faq->id)->update(['question' => $newQuestion]);
        });

        // Update Questions - Replace Aboki with VendLike AI
        DB::table('faqs')->where('question', 'like', '%Aboki%')->get()->each(function ($faq) {
            $newQuestion = str_ireplace('Aboki', 'VendLike AI', $faq->question);
            DB::table('faqs')->where('id', $faq->id)->update(['question' => $newQuestion]);
        });

        // Update Answers - Replace Kobopoint with VendLike
        DB::table('faqs')->where('answer', 'like', '%Kobopoint%')->orWhere('answer', 'like', '%kobopoint%')->get()->each(function ($faq) {
            $newAnswer = str_ireplace(['Kobopoint', 'kobopoint'], 'VendLike', $faq->answer);
            DB::table('faqs')->where('id', $faq->id)->update(['answer' => $newAnswer]);
        });

        // Update Answers - Replace Aboki with VendLike AI
        DB::table('faqs')->where('answer', 'like', '%Aboki%')->get()->each(function ($faq) {
            $newAnswer = str_ireplace('Aboki', 'VendLike AI', $faq->answer);
            DB::table('faqs')->where('id', $faq->id)->update(['answer' => $newAnswer]);
        });

        // Update "Fund Wallet" to "Add Cash" to match app UI
        DB::table('faqs')->where('answer', 'like', '%Fund Wallet%')->get()->each(function ($faq) {
            $newAnswer = str_replace('Fund Wallet', 'Add Cash', $faq->answer);
            DB::table('faqs')->where('id', $faq->id)->update(['answer' => $newAnswer]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reversal logic could be complex due to context, so we'll leave it as a placeholder
        // or just skip it since this is a branding update.
    }
}
