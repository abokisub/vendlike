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
    public function run()
    {
        // Update Questions
        DB::table('faqs')->where('question', 'like', '%VendLike%')->get()->each(function ($faq) {
            $newQuestion = str_replace('VendLike', 'VendLike', $faq->question);
            DB::table('faqs')->where('id', $faq->id)->update(['question' => $newQuestion]);
        });

        DB::table('faqs')->where('question', 'like', '%Aboki%')->get()->each(function ($faq) {
            $newQuestion = str_replace('Aboki', 'VendLike AI', $faq->question);
            DB::table('faqs')->where('id', $faq->id)->update(['question' => $newQuestion]);
        });

        // Update Answers
        DB::table('faqs')->where('answer', 'like', '%VendLike%')->get()->each(function ($faq) {
            $newAnswer = str_replace('VendLike', 'VendLike', $faq->answer);
            DB::table('faqs')->where('id', $faq->id)->update(['answer' => $newAnswer]);
        });

        DB::table('faqs')->where('answer', 'like', '%Aboki%')->get()->each(function ($faq) {
            $newAnswer = str_replace('Aboki', 'VendLike AI', $faq->answer);
            DB::table('faqs')->where('id', $faq->id)->update(['answer' => $newAnswer]);
        });

        // Specific case-insensitive cleanup
        DB::table('faqs')->where('question', 'like', '%vendlike%')->get()->each(function ($faq) {
            $newQuestion = str_ireplace('vendlike', 'VendLike', $faq->question);
            DB::table('faqs')->where('id', $faq->id)->update(['question' => $newQuestion]);
        });

        DB::table('faqs')->where('answer', 'like', '%vendlike%')->get()->each(function ($faq) {
            $newAnswer = str_ireplace('vendlike', 'VendLike', $faq->answer);
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
