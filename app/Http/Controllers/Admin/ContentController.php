<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Content\Announcement;
use App\Models\Content\SitePage;
use App\Services\Admin\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The website's pages and the announcements to members, edited by admins
 * without a developer (specification sections 11 and 12). Changes are audited.
 */
class ContentController extends Controller
{
    public function pages(): Response
    {
        return Inertia::render('admin/content/pages', [
            'pages' => SitePage::orderByRaw("CASE slug WHEN 'home' THEN 0 WHEN 'about' THEN 1 WHEN 'contact' THEN 2 WHEN 'privacy' THEN 3 ELSE 4 END")->get()
                ->map(fn (SitePage $p) => ['slug' => $p->slug, 'name' => SitePage::SLUGS[$p->slug] ?? $p->slug, 'title' => $p->title, 'updated_at' => $p->updated_at?->toDateTimeString(),
                    'url' => $p->slug === 'home' ? route('home') : route('page.'.$p->slug)]),
        ]);
    }

    public function edit(SitePage $page): Response
    {
        return Inertia::render('admin/content/page-edit', [
            'page' => $page->only(['slug', 'title', 'intro', 'body', 'image', 'seo_title', 'seo_description']) + ['name' => SitePage::SLUGS[$page->slug] ?? $page->slug],
            'url' => $page->slug === 'home' ? route('home') : route('page.'.$page->slug),
        ]);
    }

    public function update(Request $request, SitePage $page, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'intro' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:20000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['boolean'],
        ]);
        $old = $page->only(['title', 'intro', 'body', 'image', 'seo_title', 'seo_description']);
        $values = collect($data)->only(['title', 'intro', 'body', 'seo_title', 'seo_description'])->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();
        if ($request->hasFile('image')) {
            $name = $request->file('image')->storeAs('pages', Str::uuid().'.'.$request->file('image')->guessExtension(), 'uploads');
            $values['image'] = '/uploads/'.$name;
        } elseif ($request->boolean('remove_image')) {
            $values['image'] = null;
        }
        if (array_key_exists('image', $values) && $page->image && str_starts_with($page->image, '/uploads/')) {
            Storage::disk('uploads')->delete(substr($page->image, strlen('/uploads/')));   // the old photo, if it was uploaded
        }
        $page->fill($values + ['updated_by_admin_id' => $this->admin($request)->id])->save();
        $audit->record($this->admin($request), 'content.page_edit', $page, $old, $page->only(array_keys($old)));

        return back()->with('status', 'Page saved.');
    }

    public function announcements(): Response
    {
        return Inertia::render('admin/content/announcements', [
            'announcements' => Announcement::latest('id')->limit(100)->get()->map(fn (Announcement $a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'tone' => $a->tone, 'is_active' => $a->is_active,
                'starts_at' => $a->starts_at?->format('Y-m-d\TH:i'), 'ends_at' => $a->ends_at?->format('Y-m-d\TH:i'),
                'showing' => Announcement::showing()->whereKey($a->id)->exists(),
            ]),
        ]);
    }

    public function saveAnnouncement(Request $request, Audit $audit, ?Announcement $announcement = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'tone' => ['required', Rule::in(['info', 'important'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ]);
        $announcement ??= new Announcement(['created_by_admin_id' => $this->admin($request)->id]);
        $old = $announcement->exists ? $announcement->only(['title', 'body', 'tone', 'is_active']) : null;
        $announcement->fill([
            'title' => trim($data['title']), 'body' => trim($data['body']), 'tone' => $data['tone'], 'is_active' => $request->boolean('is_active'),
            'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null,
        ])->save();
        $audit->record($this->admin($request), $old ? 'content.announcement_edit' : 'content.announcement_create', $announcement, $old, $announcement->only(['title', 'body', 'tone', 'is_active']));

        return back()->with('status', 'Announcement saved.');
    }

    public function deleteAnnouncement(Request $request, Announcement $announcement, Audit $audit): RedirectResponse
    {
        $audit->record($this->admin($request), 'content.announcement_delete', $announcement, $announcement->only(['title', 'body']), null);
        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin */
        return $request->user('admin');
    }
}
