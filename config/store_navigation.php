<?php

$item = static fn (string $title, string $path, string $icon, bool $enabled = true): array => compact('title', 'path', 'icon', 'enabled');
$group = static fn (string $title, string $icon, array $items): array => compact('title', 'icon', 'items');

return [
    'groups' => [
        $group('工作台', 'layout-grid', [
            $item('总览仪表盘', '/', 'circle-gauge'),
            $item('品牌资料', '/workspace/brand', 'image'),
            $item('亚马逊', '/ecommerce/amazon', 'shopping-bag'),
            $item('Shopify', '/ecommerce/shopify', 'store'),
            $item('学生折扣', '/ecommerce/student-discounts', 'star'),
            $item('活动主题', '/ads/campaign', 'megaphone'),
            $item('AI 营销大脑', '/workspace/ai-brain', 'bot'),
        ]),
        $group('付费广告', 'megaphone', [
            $item('广告目标', '/ads/target', 'target'),
            $item('Facebook Ads', '/ads/facebook', 'briefcase'),
            $item('Google Ads', '/ads/google', 'briefcase'),
            $item('TikTok Ads', '/ads/tiktok', 'briefcase'),
            $item('Bing Ads', '/ads/bing', 'search'),
            $item('Criteo', '/ads/criteo', 'circle-gauge'),
        ]),
        $group('自然流量', 'bar-chart', [
            $item('SEO / GEO', '/organic/seo', 'search'),
            $item('品牌官媒', '/organic/social', 'message-square'),
            $item('红人运营', '/organic/kol', 'users'),
            $item('EDM 邮件', '/organic/edm', 'mail'),
            $item('联盟营销', '/organic/affiliate', 'star'),
        ]),
        $group('中台', 'shopping-cart', [
            $item('视觉设计', '/ecommerce/design', 'image'),
        ]),
        $group('舆情监控', 'message-square', [
            $item('舆情总览', '/reputation/overview', 'bar-chart'),
            $item('Google 直评', '/reputation/google-reviews', 'message-square'),
            $item('Trustpilot 评价', '/reputation/trustpilot', 'star'),
            $item('官网评论追踪', '/reputation/website-reviews', 'message-square'),
            $item('Reddit 运营', '/reputation/reddit', 'message-square'),
            $item('风险同步', '/reputation/risk-sync', 'badge-alert'),
            $item('周报生成', '/reputation/weekly-report', 'clipboard-list'),
        ]),
        $group('协作', 'clipboard-list', [
            $item('任务看板', '/collab/kanban', 'clipboard-list'),
            $item('团队成员', '/collab/team', 'users'),
        ]),
        $group('插件管理', 'blocks', [
            $item('macfox-student-discount', '/plugins/macfox-student-discount', 'graduation-cap'),
        ]),
        $group('店铺设置', 'shopping-bag', [
            $item('基础与凭证', '/store-settings', 'settings'),
        ]),
    ],
    'global_groups' => [
        $group('人员管理', 'users', [
            $item('人员档案', '/employees', 'users'),
            $item('角色与权限', '/roles', 'lock'),
        ]),
        $group('系统设置', 'settings', [
            $item('系统配置', '/settings', 'settings'),
            $item('店铺管理', '/stores', 'store'),
        ]),
    ],
];
