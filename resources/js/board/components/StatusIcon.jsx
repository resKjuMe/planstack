import React from 'react';

// Renders a status icon from the inner SVG markup delivered in the board payload
// (workflow.icons[status]). The markup comes from our own finite icon palette
// (app/Support/StatusIcons.php), so injecting it as innerHTML is safe. Lucide
// line style — same wrapper attributes as the user-menu icons.
export default function StatusIcon({ svg, className = 'h-4 w-4' }) {
    if (! svg) return null;
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: svg }}
        />
    );
}
