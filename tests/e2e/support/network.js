export async function emulateDegradedConnection(page) {
    const session = await page.context().newCDPSession(page);

    await session.send('Network.enable');
    await session.send('Network.emulateNetworkConditions', {
        offline: false,
        latency: 400,
        downloadThroughput: (400 * 1024) / 8,
        uploadThroughput: (400 * 1024) / 8,
        connectionType: 'cellular3g',
    });
}
