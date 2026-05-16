<?php

namespace Tests\Unit;

use Tests\TestCase;

class NavProfileMenuAssetsTest extends TestCase
{
    public function test_profile_menu_exposes_required_actions_and_dropdown_hooks(): void
    {
        $template = file_get_contents(resource_path('views/components/nav.blade.php'));
        $script = file_get_contents(resource_path('js/profile-menu.ts'));

        $this->assertStringContainsString('data-profile-menu-toggle', $template);
        $this->assertStringContainsString('data-profile-menu', $template);
        $this->assertStringContainsString('Настройки', $template);
        $this->assertStringContainsString('Профиль', $template);
        $this->assertStringContainsString('Выйти', $template);
        $this->assertStringContainsString('profile-menu-item-icon', $template);
        $this->assertStringContainsString('profile-menu-logout', $template);
        $this->assertStringContainsString('event.key === "Escape"', $script);
    }
}
