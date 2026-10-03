<?php

function render_footer_content()
{
    global $settings;
    ob_start();

    echo '<p class="footer-text">';
    $footer_text = $settings['footer_text'] ?? 'Developed with ♥ • &copy; {year}';
    if (strpos($footer_text, '{year}') !== false) {
        $footer_text = str_replace('{year}', date('Y'), $footer_text);
    }
    echo $footer_text;

    if (!empty($settings['footer_show_login'])) {
        echo ' | <span><a target="_blank" href="' . getBaseUrl() . 'admin/auth.php">Login</a></span>';
    }
    echo '</p>';

    if (!empty($settings['footer_show_social']) && !empty($settings['footer_social_links'])) {
        echo '<div class="social-links">';
        foreach ($settings['footer_social_links'] as $social) {
            if (!empty($social['platform']) && !empty($social['url'])) {
                echo '<a href="' . hsc($social['url']) . '" class="social-icon" target="_blank">'
                   . get_social_icon($social['platform']) . '</a>';
            }
        }
        echo '</div>';
    }

    echo '<p class="snk-credit">Powered by <a href="https://synaptikcms.com" target="_blank" rel="noopener">Synaptik CMS</a></p>';

    return ob_get_clean();
}

function get_social_icon($platform)
{
    switch (strtolower($platform)) {
        case 'instagram':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>';
        case 'twitter':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>';
        case 'x':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l16 16M20 4L4 20"/></svg>';
        case 'github':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>';
        case 'facebook':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>';
        case 'linkedin':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>';
        case 'youtube':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-1.96C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.4 19.54C5.12 20 12 20 12 20s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98" fill="currentColor" stroke="none"/></svg>';
        case 'tiktok':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>';
        case 'discord':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/><path d="M18 5c1.5 3 2 6.5 1.5 8.5-.5 2-2 3.5-3.5 4L15 16M6 5C4.5 8 4 11.5 4.5 13.5c.5 2 2 3.5 3.5 4L9 16"/><path d="M9.5 16.5c.8.5 1.6.8 2.5.8s1.7-.3 2.5-.8"/></svg>';
        case 'whatsapp':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9.1 9a.5.5 0 0 1 .9 0l.5 1a5 5 0 0 0 3.5 3.5l1 .5a.5.5 0 0 1 0 .9l-.5.3a2 2 0 0 1-2 0 9 9 0 0 1-3.9-3.9 2 2 0 0 1 0-2z"/></svg>';
        case 'snapchat':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a6 6 0 0 0-6 6v4l-2 3h4a4 4 0 0 0 8 0h4l-2-3V8a6 6 0 0 0-6-6z"/></svg>';
        case 'pinterest':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 20l4-9"/><path d="M10.7 14c.437 1.263 1.43 2 2.55 2 2.071 0 3.75-1.554 3.75-4a5 5 0 1 0-9.999 0c0 1.993.583 3.092 2 4"/></svg>';
        case 'threads':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M5 12c0-7 14-7 14 0s-7 11-12 6"/></svg>';
        case 'twitch':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2H3v16l4-4h14V2z"/><line x1="9.5" y1="9" x2="9.5" y2="14"/><line x1="14.5" y1="9" x2="14.5" y2="14"/></svg>';
        case 'telegram':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2" fill="currentColor" stroke="none"/></svg>';
        case 'reddit':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="7"/><circle cx="9.5" cy="12.5" r="1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="12.5" r="1" fill="currentColor" stroke="none"/><path d="M9.5 16a5 5 0 0 0 5 0"/><path d="M12 6V4"/><circle cx="14" cy="4" r="1" fill="currentColor" stroke="none"/></svg>';
        case 'mastodon':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.94 11c.17-1.16.25-2.34.25-3.53 0-3.59-2.36-4.65-2.36-4.65-2.36-.97-6.46-.97-8.83 0 0 0-2.36 1.06-2.36 4.65 0 5.8.34 8.84 3.74 9.8 1.5.44 2.8.53 3.82.47 1.88-.11 2.94-.68 2.94-.68L18 15.5s-1.35.44-2.86.38c-1.5-.05-3.08-.16-3.31-2a4 4 0 0 1-.04-.53s1.47.37 3.33.46c1.14.05 2.21-.06 3.3-.2 2.08-.25 3.89-1.54 4.12-2.72.36-1.84.33-4.5.33-4.5"/><path d="M16.5 8v4M13.5 8v4"/></svg>';
        case 'bluesky':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9C9.5 6 7 4 5 4a4 4 0 0 0 0 8c2 0 4.5-2 7-3z"/><path d="M12 9c2.5-3 5-5 7-5a4 4 0 0 1 0 8c-2 0-4.5-2-7-3z"/><path d="M12 9v11"/></svg>';
        default:
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';
    }
}

