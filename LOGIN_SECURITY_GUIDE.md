# OpenPOS Login Security Improvements

## Overview
Enhanced login system dengan fitur keamanan tingkat enterprise:
- ✅ Brute force attack protection
- ✅ Account lockout system
- ✅ Session timeout management
- ✅ Login attempt logging & audit trail
- ✅ Suspicious login detection
- ✅ IP-based tracking

---

## 🔒 Features Added

### 1. Brute Force Protection
**Purpose**: Mencegah automatic password guessing attacks

**How it works**:
- Track failed login attempts per username + IP
- Lock account setelah 5 failed attempts (configurable)
- Lockout duration: 15 menit default (configurable)
- Auto-unlock setelah waktu lockout expired

**Response untuk user yg ter-lock**:
```json
{
  "status": 0,
  "message": "Account temporarily locked. Please try again in 15 minutes."
}
```

---

### 2. Session Timeout
**Purpose**: Auto-logout untuk inactive sessions (security best practice)

**How it works**:
- Default timeout: 8 jam (480 menit) - configurable di admin settings
- Setiap kali user call `login-session` endpoint, sistem check jika session sudah expired
- Expired session automatically dibersihkan

**Response untuk expired session**:
```json
{
  "status": 0,
  "message": "Your session has expired. Please login again."
}
```

**Frontend integration**:
Login response sekarang include:
```json
{
  "session_remaining_seconds": 28800,
  "session_expires_at": 1713096000
}
```
Frontend bisa show countdown timer atau warning sebelum timeout.

---

### 3. Login Attempt Logging
**Purpose**: Audit trail untuk compliance & security investigation

**Database table**: `wp_openpos_login_logs`

**Fields tracked**:
- `username` - POS username
- `user_id` - WordPress user ID
- `ip_address` - Login IP
- `success` - Login berhasil? (1 = yes, 0 = no)
- `reason` - Alasan (Invalid credentials, Account locked, Session timeout, etc)
- `locked` - Apakah account ter-lock? (1 = yes)
- `session_id` - Session yang di-create
- `user_agent` - Browser/client info
- `login_time` - Timestamp login attempt

**Query example** untuk see login history:
```php
// Di WordPress admin terminator atau plugin code:
global $wpdb;
$logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}openpos_login_logs WHERE user_id = %d ORDER BY login_time DESC LIMIT 20",
        $user_id
    )
);
```

---

### 4. Suspicious Login Detection
**Purpose**: Alert untuk unusual login patterns

**Detects**:
1. **IP Change** - Login dari IP baru (sejak last login)
2. **Impossible Travel** - Login dari IP beda dalam < 2 menit
   
**Data included di login response**:
```json
{
  "suspicious_activity": true,
  "suspicious_flags": ["ip_changed", "impossible_travel"],
  "last_ip": "192.168.1.100",
  "last_login": "2026-04-14 10:30:00"
}
```

Frontend/Admin bisa trigger additional verification (2FA, re-confirm password) jika suspicious.

---

## ⚙️ Admin Configuration

### Settings Location
**Path**: WordPress Admin → OpenPOS → Settings → POS Layout tab

### Configurable Parameters

#### 1. Max Login Attempts
- **Default**: 5
- **Description**: Berapa banyak gagal login sebelum account ter-lock
- **Recommended**: 3-5 (lebih strict untuk security)

#### 2. Account Lockout Duration (Minutes)
- **Default**: 15
- **Description**: Berapa lama account ter-lock
- **Recommended**: 15-30 menit

#### 3. Session Timeout (Minutes)
- **Default**: 480 (8 jam)
- **Description**: Auto-logout inactive POS sessions
- **Recommended**: 240 (4 jam) untuk high-security, 480-1440 untuk normal retail

#### 4. Enable Login Attempt Logging
- **Default**: Yes
- **Description**: Catat semua login attempts ke database
- **Note**: Disable jika server storage terbatas (tapi not recommended)

#### 5. Detect Suspicious Logins
- **Default**: Yes
- **Description**: Identifikasi unusual login patterns
- **Benefit**: Backend akan flag suspicious logins, frontend bisa show warning

---

## 📝 Implementation Details

### New File: `LoginSecurity.php`
Class `OP_LoginSecurity` dengan public methods:

```php
// Check jika account ter-lock
$lockout = $login_security->check_lockout($username, $ip);
// Returns: ['locked' => bool, 'remaining_time' => int, 'attempts_left' => int]

// Record failed attempt
$login_security->record_failed_attempt($username, $ip);

// Record successful login
$login_security->record_successful_login($user_id, $username, $ip, $session_id);

// Validate session timeout
$valid = $login_security->validate_session_timeout($session_data, $session_id);
// Returns: ['valid' => bool, 'message' => string, 'expired_at' => int, 'remaining_seconds' => int]

// Get login history
$history = $login_security->get_login_history($user_id, 20);

// Check suspicious activity
$suspicious = $login_security->check_suspicious_activity($user_id, $ip);
// Returns: ['suspicious' => bool, 'flags' => array, 'last_ip' => string, 'last_login' => string]
```

### Modified Files: 
1. **Auth.php** - `login()` function:
   - Initialize LoginSecurity
   - Check lockout status BEFORE authentication
   - Record failed/successful attempts
   - Detect suspicious activity
   - Include session timeout info di response

2. **Auth.php** - `login_session()` function:
   - Validate session timeout
   - Return remaining session time

3. **Admin.php** - `get_settings_fields()`:
   - Add 5 new settings fields untuk login security configuration

---

## 🚀 Usage Examples

### From Frontend (JavaScript/React)
```javascript
// Login dengan brute force protection
async function loginUser(username, password) {
  try {
    const response = await fetch('/wp-json/openpos/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        username, 
        password,
        login_mode: 'default',
        location: 'Main Store'
      })
    });
    
    const data = await response.json();
    
    if (data.response.status === 1) {
      // Login successful
      sessionStorage.setItem('session_id', data.response.data.session);
      sessionStorage.setItem('session_expires', data.response.data.session_expires_at * 1000);
      
      // Show timeout warning
      startSessionTimer(data.response.data.session_remaining_seconds);
      
      // Check untuk suspicious activity
      if (data.response.data.suspicious_activity) {
        console.warn('Suspicious login detected:', data.response.data.suspicious_flags);
        // Bisa trigger 2FA verification
      }
    } else {
      // Login failed
      console.error(data.response.message);
      // Handle brute force lockout message
      showError(data.response.message);
    }
  } catch (error) {
    console.error('Login error:', error);
  }
}

// Validate session jika tidak ada activity
function startSessionTimer(remainingSeconds) {
  const expireTime = Date.now() + (remainingSeconds * 1000);
  
  const warningTime = expireTime - (5 * 60 * 1000); // Warn 5 menit before
  
  const timer = setInterval(() => {
    const now = Date.now();
    
    if (now >= expireTime) {
      // Session expired
      clearInterval(timer);
      location.reload(); // Redirect ke login
    } else if (now >= warningTime) {
      // Show warning
      showWarning('Your session will expire soon. Please save your work.');
    }
  }, 60000); // Check every minute
}
```

### From WordPress Admin (PHP)
```php
// Get user login history
require_once(plugin_dir_path(__FILE__) . 'includes/api/LoginSecurity.php');
$login_security = new OP_LoginSecurity();

$history = $login_security->get_login_history($user_id, 10);
foreach ($history as $log) {
  echo "User: {$log->username}\n";
  echo "IP: {$log->ip_address}\n";
  echo "Time: {$log->login_time}\n";
  echo "Success: " . ($log->success ? 'Yes' : 'No') . "\n";
  echo "---\n";
}

// Get settings
$settings = $login_security->get_settings();
echo "Max attempts: " . $settings['max_attempts'] . "\n";
echo "Session timeout: " . $settings['session_timeout_minutes'] . " minutes\n";
```

---

## 🔐 Security Best Practices

### Recommendations:
1. **Enable login logging** - Always untuk audit trail
2. **Set shorter session timeouts** untuk high-value transactions (4 jam)
3. **Enable suspicious login detection** untuk instant notification
4. **Use HTTPS only** - LoginSecurity tracking IP, pastikan traffic encrypted
5. **Review login logs regularly** - Check admin dashboard untuk suspicious patterns
6. **Enable 2FA** - Kombinasi dengan login security untuk extra protection

### WARNING:
⚠️ **DO NOT** disable login security features di production environment dengan multi-user cashiers

---

## 📊 Monitoring

### Database Query untuk monitoring:
```sql
-- Failed login attempts last 24 hours
SELECT username, ip_address, COUNT(*) as attempts, MAX(login_time) as last_attempt
FROM wp_openpos_login_logs
WHERE success = 0 AND login_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY username, ip_address
ORDER BY attempts DESC;

-- Locked accounts
SELECT username, ip_address, login_time
FROM wp_openpos_login_logs
WHERE locked = 1 AND login_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY login_time DESC;

-- Suspicious logins
SELECT username, ip_address, reason, login_time
FROM wp_openpos_login_logs
WHERE reason LIKE '%suspicious%' OR reason LIKE '%impossible%'
ORDER BY login_time DESC;
```

---

## ✅ Testing Checklist

- [ ] Admin settings fields visible di POS Layout tab
- [ ] Failed login attempts trigger lockout after configured max
- [ ] Locked account shows timeout message
- [ ] Successful login records di database
- [ ] Session timeout validates di login_session endpoint
- [ ] Suspicious login detected untuk IP change
- [ ] Login logs table created automatically
- [ ] Can configure all security settings dari admin

---

## 💡 Future Enhancements

1. **Two-Factor Authentication (2FA)** - SMS/Email code verification
2. **IP Whitelist** - Allow login hanya dari specific IPs
3. **Password Policy** - Enforce strong passwords, expiry
4. **Device Fingerprinting** - Track device tokens
5. **Geo-blocking** - Block login dari specific countries
6. **Admin Dashboard Widget** - Visual login attempt charts
7. **Email Notifications** - Alert admin untuk suspicious activity
8. **Export Logs** - Download login audit trail untuk compliance

---

## 📧 Support

Untuk issues atau questions:
1. Check login logs di database
2. Enable WordPress debug logging
3. Review session_data jika timeout issues
4. Verify plugin permissions untuk table creation

