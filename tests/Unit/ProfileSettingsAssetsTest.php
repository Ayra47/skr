<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProfileSettingsAssetsTest extends TestCase
{
    public function test_settings_page_exposes_profile_visibility_controls(): void
    {
        $template = file_get_contents(resource_path('views/pages/settings/index.blade.php'));
        $script = file_get_contents(resource_path('js/pages/settings.js'));

        $this->assertStringContainsString('Видимость профиля', $template);
        $this->assertStringContainsString('Показывать общие чаты', $template);
        $this->assertStringContainsString('Показывать общие группы', $template);
        $this->assertStringContainsString('Кто может открыть мой профиль?', $template);
        $this->assertStringContainsString('Показывать ли аватар?', $template);
        $this->assertStringContainsString('initProfileVisibility', $script);
        $this->assertStringContainsString('/settings/profile/visibility', $script);
    }
}
