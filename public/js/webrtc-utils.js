export function normalizeSdp(sdp) {
    if (typeof sdp !== 'string') {
        return sdp;
    }

    let normalized = sdp.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    normalized = normalized.replace(/\n+$/, '');

    return normalized.replace(/\n/g, '\r\n') + '\r\n';
}

export function normalizeDescription(description) {
    if (!description || !description.type || typeof description.sdp !== 'string') {
        return description;
    }

    return {
        type: description.type,
        sdp: normalizeSdp(description.sdp),
    };
}
