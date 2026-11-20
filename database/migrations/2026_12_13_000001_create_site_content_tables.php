<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The public website's pages, which admins edit without a developer, and
 * announcements to members (specification sections 11 and 12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();      // home, about, contact, privacy, terms
            $table->string('title', 150);
            $table->string('intro', 300)->nullable();  // the short line under the title
            $table->text('body')->nullable();          // paragraphs; "## " starts a heading, "- " a list item
            $table->string('image')->nullable();       // public path, e.g. /uploads/pages/x.jpg
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('tone', 10)->default('info');   // info, important
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $pages = [
            ['home', 'Grow with Wiifarm Cooperative', 'Quality farm products, a member network that rewards you, and a cooperative that works for its members.',
                "## What we do\nWiifarm Cooperative brings members quality products made from farm produce, such as VitaActive and VitaForce, and shares the rewards of every sale across its member network.\n\n## How it works\n- Join with a package and receive its products.\n- Buy your monthly products and collect them from any Wiifarm stockist with your pickup code.\n- Earn bonuses as your team grows.",
                '/images/brand/vitaactive-range.jpg', 'Wiifarm Cooperative', 'Quality farm products and a member network that rewards you.'],
            ['about', 'About Wiifarm', 'A cooperative built on farm produce and its members.',
                "Wiifarm Cooperative is owned by and run for its members.\n\nThis page is edited by Wiifarm's admins under Admin > Website.",
                null, 'About Wiifarm Cooperative', null],
            ['contact', 'Contact us', 'We are happy to help.',
                "## Members\nSign in and use Support to send us a message.\n\n## Everyone else\nWiifarm's address, phone number and email are added here by the admins.",
                null, 'Contact Wiifarm Cooperative', null],
            ['privacy', 'Privacy policy', null,
                "This policy explains what personal information Wiifarm Cooperative collects and how it is used.\n\nWiifarm's admins replace this text with the cooperative's own policy, reviewed by its legal adviser, before the site goes live.",
                null, 'Privacy policy', null],
            ['terms', 'Terms of use', null,
                "These terms govern the use of the Wiifarm Cooperative website and membership.\n\nWiifarm's admins replace this text with the cooperative's own terms, reviewed by its legal adviser, before the site goes live.",
                null, 'Terms of use', null],
        ];
        foreach ($pages as [$slug, $title, $intro, $body, $image, $seoTitle, $seoDescription]) {
            DB::table('site_pages')->insert([
                'slug' => $slug, 'title' => $title, 'intro' => $intro, 'body' => $body, 'image' => $image,
                'seo_title' => $seoTitle, 'seo_description' => $seoDescription, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('site_pages');
    }
};
