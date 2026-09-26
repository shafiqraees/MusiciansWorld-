<?php
/** Small inline-SVG icon set. No icon font / CDN dependency. */
function icon(string $name, string $class = ""): string
{
    $paths = [
        "users" => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c0-3 2.5-5.2 5.5-5.2s5.5 2.2 5.5 5.2"/><circle cx="17" cy="8.5" r="2.6"/><path d="M15.2 13.4c2.6.3 4.8 2.4 4.8 5.1"/>',
        "crown" => '<path d="M4 18h16l1.2-9-5 3.4L12 6l-4.2 6.4-5-3.4L4 18Z"/><path d="M4 18v1.6h16V18"/>',
        "cap" => '<path d="M12 5 2.5 9.5 12 14l9.5-4.5L12 5Z"/><path d="M6.5 11.7V16c0 1.4 2.5 2.8 5.5 2.8s5.5-1.4 5.5-2.8v-4.3"/><path d="M21.5 9.5v5.4"/>',
        "trending" => '<path d="M3 17 9.5 10.5l4 4L21 6.5"/><path d="M15 6.5h6v6"/>',
        "target" => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.8"/><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/>',
        "bars" => '<path d="M5 19v-6"/><path d="M12 19V7"/><path d="M19 19v-10"/>',
        "gear" => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.3M12 18.2v2.3M4.6 7l2 1.15M17.4 15.85l2 1.15M4.6 17l2-1.15M17.4 8.15l2-1.15M3.5 12h2.3M18.2 12h2.3"/>',
        "calendar" => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17"/><path d="M8 3v3.4M16 3v3.4"/>',
        "check" => '<path d="M5 12.5 9.5 17 19 6.5"/>',
    ];
    $body = $paths[$name] ?? "";
    if ($body === "") {
        return "";
    }
    $classAttr = $class !== "" ? ' class="' . e($class) . '"' : "";
    return '<svg' . $classAttr . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . "</svg>";
}
