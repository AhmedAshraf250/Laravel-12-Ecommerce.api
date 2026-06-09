<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Reverb Debug</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f1e8;
            --card: #fffaf1;
            --ink: #1f2937;
            --muted: #6b7280;
            --accent: #ad5c2f;
            --border: #e7d8bf;
            --success: #1f7a43;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top left, #fff8eb 0, transparent 35%),
                linear-gradient(180deg, #f7f0e4 0%, var(--bg) 100%);
            color: var(--ink);
        }

        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(2rem, 3vw, 3rem);
        }

        p {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.6;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 18px 40px rgba(69, 42, 16, 0.08);
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        label {
            display: block;
            font-size: 0.95rem;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fff;
            font: inherit;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        button {
            border: 0;
            border-radius: 999px;
            padding: 11px 18px;
            cursor: pointer;
            font: inherit;
            transition: transform 120ms ease, opacity 120ms ease;
        }

        button:hover { transform: translateY(-1px); }
        .primary { background: var(--accent); color: white; }
        .secondary { background: #efe2cc; color: var(--ink); }

        .status {
            font-weight: 700;
            color: var(--success);
            min-height: 1.5em;
        }

        pre {
            margin: 0;
            padding: 16px;
            overflow: auto;
            border-radius: 14px;
            background: #1b1b1b;
            color: #f8f8f2;
            min-height: 280px;
            font-size: 0.92rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Order Status Reverb Debug</h1>
        <p>Paste a Sanctum bearer token, add an order ID, optionally add a user ID, then connect and trigger an order status change from the API.</p>

        <div class="card">
            <div class="grid">
                <div>
                    <label for="token">Bearer token</label>
                    <input id="token" placeholder="Paste CUSTOMER_TOKEN or ADMIN_TOKEN">
                </div>
                <div>
                    <label for="order-id">Order ID</label>
                    <input id="order-id" placeholder="Example: 1">
                </div>
                <div>
                    <label for="user-id">User ID (optional)</label>
                    <input id="user-id" placeholder="Example: 2">
                </div>
            </div>

            <div class="actions">
                <button class="primary" id="connect">Connect</button>
                <button class="secondary" id="disconnect">Disconnect</button>
                <button class="secondary" id="clear-log">Clear log</button>
            </div>

            <p class="status" id="status">Idle</p>
        </div>

        <div class="card">
            <pre id="log">Waiting for events...</pre>
        </div>
    </div>

    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script>
        let pusherInstance = null;
        let orderChannel = null;
        let userOrdersChannel = null;

        const tokenInput = document.getElementById('token');
        const orderIdInput = document.getElementById('order-id');
        const userIdInput = document.getElementById('user-id');
        const statusNode = document.getElementById('status');
        const logNode = document.getElementById('log');

        const appKey = @json(config('broadcasting.connections.reverb.key'));
        const wsHost = @json(config('broadcasting.connections.reverb.options.host'));
        const wsPort = @json((int) env('REVERB_PORT', 8080));
        const scheme = @json(config('broadcasting.connections.reverb.options.scheme'));
        const authEndpoint = @json(url('/broadcasting/auth'));
        const cluster = @json(env('PUSHER_APP_CLUSTER', 'mt1'));

        function writeLog(message, payload = null) {
            const timestamp = new Date().toLocaleTimeString();
            const line = `[${timestamp}] ${message}`;
            const body = payload ? `\n${JSON.stringify(payload, null, 2)}` : '';
            logNode.textContent = `${line}${body}\n\n${logNode.textContent === 'Waiting for events...' ? '' : logNode.textContent}`;
        }

        function disconnect() {
            if (orderChannel && pusherInstance) {
                pusherInstance.unsubscribe(orderChannel.name);
                orderChannel = null;
            }

            if (userOrdersChannel && pusherInstance) {
                pusherInstance.unsubscribe(userOrdersChannel.name);
                userOrdersChannel = null;
            }

            if (pusherInstance) {
                pusherInstance.disconnect();
                pusherInstance = null;
            }

            statusNode.textContent = 'Disconnected';
        }

        function connect() {
            disconnect();

            statusNode.textContent = 'Preparing connection...';

            const token = tokenInput.value.trim();
            const orderId = orderIdInput.value.trim();
            const userId = userIdInput.value.trim();

            if (!token || !orderId) {
                statusNode.textContent = 'Token and order ID are required.';
                return;
            }

            if (typeof Pusher === 'undefined') {
                statusNode.textContent = 'Pusher library failed to load.';
                writeLog('Pusher is not available on window. Check internet access or blocked CDN script.');
                return;
            }

            try {
                Pusher.logToConsole = false;

                pusherInstance = new Pusher(appKey, {
                    cluster: cluster,
                    wsHost: wsHost,
                    wsPort: wsPort,
                    wssPort: wsPort,
                    forceTLS: scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    authEndpoint: authEndpoint,
                    auth: {
                        headers: {
                            Accept: 'application/json',
                            Authorization: `Bearer ${token}`,
                        },
                    },
                });

                pusherInstance.connection.bind('state_change', ({ previous, current }) => {
                    statusNode.textContent = `Connection state: ${current}`;
                    writeLog('Connection state changed', { previous, current });
                });

                pusherInstance.connection.bind('connected', () => {
                    statusNode.textContent = 'Connected. Authenticating private channels...';
                    writeLog('Connected to Reverb socket');
                });

                pusherInstance.connection.bind('error', (error) => {
                    statusNode.textContent = 'Connection error';
                    writeLog('Connection error', error);
                });

                orderChannel = pusherInstance.subscribe(`private-orders.${orderId}`);
                orderChannel.bind('pusher:subscription_succeeded', () => {
                    statusNode.textContent = `Subscribed to private-orders.${orderId}`;
                    writeLog('Order channel subscription succeeded', {
                        channel: `private-orders.${orderId}`,
                    });
                });
                orderChannel.bind('pusher:subscription_error', (error) => {
                    statusNode.textContent = 'Order channel subscription failed';
                    writeLog('Order channel subscription failed', error);
                });
                orderChannel.bind('order.status.updated', (payload) => {
                    statusNode.textContent = `Received event on private-orders.${orderId}`;
                    writeLog('Order channel event received', payload);
                });

                if (userId) {
                    userOrdersChannel = pusherInstance.subscribe(`private-users.${userId}.orders`);
                    userOrdersChannel.bind('pusher:subscription_succeeded', () => {
                        statusNode.textContent = `Subscribed to private-users.${userId}.orders`;
                        writeLog('User orders channel subscription succeeded', {
                            channel: `private-users.${userId}.orders`,
                        });
                    });
                    userOrdersChannel.bind('pusher:subscription_error', (error) => {
                        statusNode.textContent = 'User orders channel subscription failed';
                        writeLog('User orders channel subscription failed', error);
                    });
                    userOrdersChannel.bind('order.status.updated', (payload) => {
                        statusNode.textContent = `Received event on private-users.${userId}.orders`;
                        writeLog('User orders channel event received', payload);
                    });
                }

                statusNode.textContent = 'Connecting...';
                writeLog('Attempting Reverb connection', {
                    order_channel: `private-orders.${orderId}`,
                    user_channel: userId ? `private-users.${userId}.orders` : null,
                    auth_endpoint: authEndpoint,
                    ws_host: wsHost,
                    ws_port: wsPort,
                });
            } catch (error) {
                statusNode.textContent = 'Connect failed';
                writeLog('Connect threw an exception', {
                    type: typeof error,
                    string_value: String(error),
                    message: error && typeof error === 'object' && 'message' in error ? error.message : null,
                    stack: error && typeof error === 'object' && 'stack' in error ? error.stack : null,
                    own_keys: error && typeof error === 'object' ? Object.getOwnPropertyNames(error) : [],
                    raw: error,
                });
            }
        }

        document.getElementById('connect').addEventListener('click', connect);
        document.getElementById('disconnect').addEventListener('click', disconnect);
        document.getElementById('clear-log').addEventListener('click', () => {
            logNode.textContent = 'Waiting for events...';
        });
    </script>
</body>
</html>
