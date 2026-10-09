    <?php
    /**
     * ============================================================
     *  Eduroad - Security Middleware
     *  Handles CSRF protection, Output Buffering Injection, and Headers
     * ============================================================
     */

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Generate CSRF Token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // 2. Validate CSRF for POST Requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        // Exempt specific routes if needed (e.g., Pusher webhooks)
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, 'pusher_auth.php') === false && strpos($uri, 'webhook') === false) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_CSRF_TOKEN'] ?? '';
            
            if (!hash_equals($_SESSION['csrf_token'], $token)) {
                if ($isAjax) {
                    header('HTTP/1.1 403 Forbidden');
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Invalid CSRF token', 'ok' => false]);
                    exit;
                } else {
                    die('
                    <div style="text-align:center; padding: 50px; font-family: sans-serif;">
                        <h2 style="color: #e3342f;">خطأ: طلب غير مصرح به (CSRF Mismatch)</h2>
                        <p>انتهت صلاحية الجلسة أو هناك محاولة لإرسال بيانات غير مصرح بها.</p>
                        <a href="javascript:history.back()" style="padding: 10px 20px; background: #3490dc; color: #fff; text-decoration: none; border-radius: 5px;">العودة والمحاولة مرة أخرى</a>
                    </div>
                    ');
                }
            }
        }
    }

    // 3. Security Headers
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");

    // 4. Output Buffer Middleware to Inject CSRF Token
    ob_start(function($buffer) {
        // Skip injection if it's a JSON response
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                return $buffer;
            }
        }
        
        $token = $_SESSION['csrf_token'];
        $input = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
        
        // Inject into all POST forms
        $buffer = preg_replace('/(<form\b[^>]*?method=[\'"]post[\'"][^>]*>)/i', '$1' . "\n" . $input, $buffer);
        
        // Inject meta tag for AJAX
        $meta = '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
        $buffer = str_ireplace('</head>', $meta . "\n</head>", $buffer);
        
        // Inject Pusher Frontend Script before </body>
        if (stripos($buffer, '</body>') !== false) {
            $pusher_user_channel = '';
            if (isset($_SESSION['user_id'])) {
                $pusher_user_channel = 'user-' . $_SESSION['user_role'] . '-' . $_SESSION['user_id'];
            } elseif (isset($_SESSION['academic_id'])) {
                $pusher_user_channel = 'user-academic-' . $_SESSION['academic_id'];
            }
            
            $myRole = $_SESSION['user_role'] ?? (isset($_SESSION['academic_id']) ? 'academic' : '');
            
            $pusherScript = '
            <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
            <script>
            (function() {
                const PUSHER_KEY = "YOUR_PUSHER_KEY";
                const PUSHER_CLUSTER = "eu";
                if(PUSHER_KEY === "YOUR_PUSHER_KEY") return;
                const pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
                
                const userChannelName = "' . $pusher_user_channel . '";
                if (userChannelName) {
                    const userChannel = pusher.subscribe(userChannelName);
                    userChannel.bind("new-notification", function(data) {
                        if (typeof Toast !== "undefined" && Toast.show) { Toast.show("🔔 " + data.title, "info"); }
                        
                        const badges = document.querySelectorAll(".notifications-badge, .badge, .notif-badge");
                        badges.forEach(b => {
                            let current = parseInt(b.innerText || 0);
                            if (!isNaN(current)) {
                                b.innerText = current + 1;
                                b.style.display = "inline-block";
                            }
                        });
                    });
                }
                
                if (typeof convId !== "undefined" && convId > 0) {
                    const chatChannel = pusher.subscribe("chat-" + convId);
                    chatChannel.bind("new-message", function(data) {
                        if (typeof appendMessage === "function") {
                            if (document.querySelector(".msg[data-msg-id=\'" + data.id + "\']")) return;
                            appendMessage(data);
                            if (typeof scrollToBottom === "function") scrollToBottom(true);
                            if (typeof lastMsgId !== "undefined" && data.id > lastMsgId) { lastMsgId = data.id; }
                        }
                    });
                }
            })();
            </script>';
            
            $buffer = str_ireplace('</body>', $pusherScript . "\n</body>", $buffer);
        }
        
        return $buffer;
    });
