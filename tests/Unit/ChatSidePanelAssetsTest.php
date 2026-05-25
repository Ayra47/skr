<?php

namespace Tests\Unit;

use Tests\TestCase;

class ChatSidePanelAssetsTest extends TestCase
{
    public function test_chat_exposes_side_panel_info_and_emoji_tabs(): void
    {
        $template = file_get_contents(resource_path('views/pages/chat/index.blade.php'));
        $shell = file_get_contents(resource_path('views/components/app-shell.blade.php'));
        $shellStyles = file_get_contents(resource_path('css/components/app-shell.scss'));
        $script = file_get_contents(resource_path('js/pages/chat/emoji.ts'));
        $messages = file_get_contents(resource_path('js/pages/chat/messages.ts'));
        $events = file_get_contents(resource_path('js/pages/chat/events.ts'));

        $this->assertStringContainsString('id="chatSidePanel"', $template);
        $this->assertStringContainsString('has-panel', $shell);
        $this->assertStringContainsString('&.has-panel', $shellStyles);
        $this->assertStringContainsString('grid-template-columns: 1fr 3fr auto', $shellStyles);
        $this->assertStringContainsString('min-height: 100dvh;', $shellStyles);
        $this->assertStringContainsString('align-self: stretch;', file_get_contents(resource_path('css/pages/chat.scss')));
        $this->assertStringContainsString('min-height: 0;', file_get_contents(resource_path('css/pages/chat.scss')));
        $this->assertStringContainsString('overflow-y: auto;', file_get_contents(resource_path('css/pages/chat.scss')));
        $this->assertStringNotContainsString('panel.style.overflow = "visible"', $script);
        $this->assertStringNotContainsString('id="groupManageBtn"', $template);
        $this->assertStringContainsString('data-latest-payload', $template);
        $this->assertStringNotContainsString('зашифровано', $template);
        $this->assertStringContainsString('data-side-panel-tab="info"', $template);
        $this->assertStringContainsString('data-side-panel-tab="emoji"', $template);
        $this->assertStringContainsString('общение с человеком', $template);
        $this->assertStringContainsString('hydrateConversationPreviews', file_get_contents(resource_path('js/pages/chat.js')));
        $this->assertStringContainsString('hydrateConversationPreviews', $messages);
        $this->assertStringNotContainsString('>зашифровано<', $messages);
        $this->assertStringContainsString('initSidePanelTabs', $script);
        $this->assertStringContainsString('group-invite-card', $messages);
        $this->assertStringContainsString('group-invites-count', $messages);
        $this->assertStringContainsString('group-title-inline', $messages);
        $this->assertStringContainsString('groupTitleEditBtn', $messages);
        $this->assertStringNotContainsString('group-panel-section group-title-edit', $messages);
        $this->assertStringNotContainsString('groupRenameBtn', $messages);
        $this->assertStringContainsString('saveGroupTitle', $events);
        $this->assertStringContainsString('Постоянная ссылка', $messages);
        $this->assertStringContainsString('groupRoleBadge', $messages);
        $this->assertStringNotContainsString('member.role === "admin" ? "админ" : "участник"', $messages);
        $this->assertStringContainsString('group-avatar-camera', $messages);
        $this->assertStringContainsString('group-avatar-camera', file_get_contents(resource_path('css/pages/chat.scss')));
        $this->assertStringContainsString('openChatHeaderInfoPanel', $events);
        $this->assertStringContainsString('target.closest(".chat-header-tools")', $events);
        $this->assertStringContainsString('closest("#groupAvatarBtn")', $events);
        $this->assertStringContainsString('groupInfoAvatar', $events);
        $this->assertStringContainsString('toggleMemberActionPopup', $events);
        $this->assertStringContainsString('group-member-row--actions-open', file_get_contents(resource_path('css/pages/chat.scss')));
    }
}
