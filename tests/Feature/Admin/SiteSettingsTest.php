<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Settings;
use Tests\Feature\Network\NetworkTestCase;

/** Sign-ups on/off, the site name and the daily backup (specification sections 11 and 12). */
class SiteSettingsTest extends NetworkTestCase
{
    public function test_sign_ups_can_be_closed()
    {
        Settings::set('site.registration_open', false);
        $this->get('/register')->assertInertia(fn ($page) => $page->where('closed', true));
        $this->post('/register', ['username' => 'late_comer'])->assertSessionHasErrors('username');
        $this->assertFalse(User::where('username', 'late_comer')->exists());

        Settings::set('site.registration_open', true);
        $this->get('/register')->assertInertia(fn ($page) => $page->where('closed', false));
    }

    public function test_the_site_name_is_shared_with_every_page()
    {
        Settings::set('site.name', 'Wiifarm Coop');
        $this->get('/')->assertInertia(fn ($page) => $page->where('name', 'Wiifarm Coop'));
    }
}
