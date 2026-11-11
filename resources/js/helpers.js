export const generateColorFromId = (id, opacity = 1) => {
    const numId = id.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);

    const hash = numId * 9301 + 49297 % 233280;

    const r = (hash % 256);
    const g = ((hash >> 8) % 256);
    const b = ((hash >> 16) % 256);
    
    return opacity < 1 
        ? `rgba(${r}, ${g}, ${b}, ${opacity})`
        : `rgb(${r}, ${g}, ${b})`;
};

export const convertFloatToHexOpacity = (opacity) => {
    return Math.round(opacity * 255)
        .toString(16)
        .padStart(2, "0");
};

export const secondsToClock = (seconds) => {
    return `${Math.floor((seconds + 1) / 60)}:${(Math.ceil(seconds) % 60)
        .toString()
        .padStart(2, "0")}`;
};

export const getCookieValue = (cookieName) => {
    // Get all cookies as a string
    const cookies = document.cookie;

    // Find the specific cookie by name
    const match = cookies.match(
        new RegExp("(^|;)\\s*" + cookieName + "\\s*=\\s*([^;]+)")
    );

    // If the cookie is found, return its value
    return match ? decodeURIComponent(match[2]) : null;
};

export const getCsrfToken = () => {
    return getCookieValue("XSRF-TOKEN");
};

export const bytesToHumanReadable = (bytes) => {
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"];

    if (bytes === 0) return "0 Byte";

    const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));

    return Math.round(bytes / Math.pow(1024, i), 2) + " " + sizes[i];
};

