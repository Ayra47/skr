export const state = {
    currentConvId: null as number | null,
    currentPartnerId: null as number | null,
    currentPartnerLogin: null as string | null,
    partnerPublicKeyCache: {} as Record<number, CryptoKey>,
    myPrivateKey: null as CryptoKey | null,
    myPublicKeyJwk: null as JsonWebKey | null,
    typingTimeout: null as ReturnType<typeof setTimeout> | null,
    oldestMessageId: null as number | null,
    onlineUsers: new Set<number>(),
};
