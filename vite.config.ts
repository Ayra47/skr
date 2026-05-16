import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.scss",
                "resources/js/app.ts",

                "resources/css/pages/chat.scss",
                "resources/css/pages/friends.scss",
                "resources/css/pages/feed.scss",
                "resources/css/pages/login.scss",
                "resources/css/pages/register.scss",
                "resources/css/pages/settings.scss",
                "resources/css/pages/notifications.scss",
                "resources/css/pages/profile.scss",

                "resources/js/pages/chat.js",
                "resources/js/pages/notifications.js",
                "resources/js/pages/feed.ts",
                "resources/js/pages/friends.js",
                "resources/js/pages/settings.js",
                "resources/js/pages/profile.ts",
                "resources/js/pages/status.js",
                "resources/js/global-notifications.js",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
