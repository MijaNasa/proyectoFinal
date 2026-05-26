export function decodeLabel(label) {
    return new DOMParser().parseFromString(label, 'text/html').body.textContent ?? label
}
