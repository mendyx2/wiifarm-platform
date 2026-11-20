<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;

/**
 * A page of the public website, edited by admins (specification section 12).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property ?string $intro
 * @property ?string $body
 * @property ?string $image
 * @property ?string $seo_title
 * @property ?string $seo_description
 */
class SitePage extends Model
{
    public const SLUGS = ['home' => 'Home page', 'about' => 'About', 'contact' => 'Contact', 'privacy' => 'Privacy policy', 'terms' => 'Terms of use'];

    protected $guarded = ['id'];

    /** What a page shows, for the browser. */
    public function toPublic(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'intro' => $this->intro, 'body' => $this->body, 'image' => $this->image];
    }

    /** The search-engine and sharing details. */
    public function meta(): array
    {
        return [
            'title' => $this->seo_title ?: $this->title,
            'description' => $this->seo_description ?: $this->intro,
            'image' => $this->image ? url($this->image) : null,
        ];
    }
}
