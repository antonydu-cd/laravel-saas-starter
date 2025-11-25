# 🔒 Security Implementation Guide

本文档说明了此Laravel SaaS脚手架的安全实现和最佳实践。

## ✅ 已实施的安全修复

### 1. API密钥加密存储 (Critical)

**问题**: API密钥以明文存储在数据库中
**修复**: 自动加密/解密敏感配置

**实现位置**:
- `src/Services/StripeService.php:34-71`
- `src/Services/LagoService.php:32-69`

**使用方式**:
```php
// 保存时自动加密
$encryptedKey = encrypt('sk_live_...');
DB::table('general_settings')->update([
    'more_configs' => json_encode([
        'stripe_secret_key' => $encryptedKey,
    ])
]);

// 读取时自动解密
$key = $this->getSetting('stripe_secret_key');
```

**涉及的敏感字段**:
- `stripe_secret_key`
- `stripe_webhook_secret`
- `lago_api_key`

---

### 2. 支付幂等性保护 (Critical)

**问题**: 支付回调可能被重复处理，导致重复扣款
**修复**: 数据库事务 + 悲观锁

**实现位置**:
- `src/Http/Controllers/Tenant/PaymentController.php:23-97`

**关键机制**:
```php
DB::transaction(function () use ($sessionId) {
    // 使用悲观锁防止并发
    $existingPayment = Payment::where('session_id', $sessionId)
        ->lockForUpdate()
        ->first();

    if ($existingPayment && $existingPayment->isCompleted()) {
        // 已处理，直接返回
        return redirect()->with('info', 'Payment already processed.');
    }

    // 标记为处理中
    if ($existingPayment) {
        $existingPayment->update(['status' => 'processing']);
    }

    // 处理支付...
}, 5); // 5次重试
```

---

### 3. 输入验证 (High)

**问题**: 用户输入未经充分验证
**修复**: FormRequest验证类

**实现位置**:
- `src/Http/Requests/PaymentSuccessRequest.php`

**验证规则**:
```php
public function rules(): array
{
    return [
        'session_id' => 'required|string|max:500',
    ];
}

// 自动清理XSS
protected function prepareForValidation(): void
{
    if ($this->has('session_id')) {
        $this->merge([
            'session_id' => strip_tags($this->input('session_id')),
        ]);
    }
}
```

---

### 4. 安全响应头 (Medium)

**问题**: 缺少安全HTTP响应头
**修复**: SecurityHeaders中间件

**实现位置**:
- `src/Http/Middleware/SecurityHeaders.php`

**包含的安全头**:
- `X-Frame-Options: DENY` - 防止点击劫持
- `X-Content-Type-Options: nosniff` - 防止MIME嗅探
- `X-XSS-Protection: 1; mode=block` - XSS保护
- `Referrer-Policy: strict-origin-when-cross-origin` - 控制Referer
- `Content-Security-Policy` - 内容安全策略
- `Strict-Transport-Security` - HTTPS强制(生产环境)

**如何启用**:
```php
// 在 app/Http/Kernel.php 或服务提供者中注册
protected $middleware = [
    \App\Http\Middleware\SecurityHeaders::class,
    // ... 其他中间件
];
```

---

### 5. HTTPS强制 (Medium)

**实现位置**:
- `src/Http/Middleware/ForceHttps.php`

**功能**: 生产环境自动重定向HTTP到HTTPS

---

### 6. 速率限制 (Medium)

**问题**: 没有API速率限制
**修复**: 路由级别限流

**实现位置**:
- `routes/tenant.php:33-44`

**限流配置**:
```php
// 支付回调: 每分钟20次
Route::middleware(['throttle:20,1'])->group(function () {
    Route::get('/success', ...);
    Route::get('/cancel', ...);
});

// Webhook: 每分钟100次
Route::post('/webhook', ...)
    ->middleware(['throttle:100,1']);
```

---

### 7. Webhook安全 (High)

**实现位置**:
- `src/Helpers/WebhookSecurityHelper.php`

**功能**:
1. **IP白名单验证**
```php
if (!WebhookSecurityHelper::isValidStripeIp($request->ip())) {
    return response()->json(['error' => 'Forbidden'], 403);
}
```

2. **签名验证** (已有)
```php
$event = $stripeService->constructWebhookEvent($payload, $signature);
```

3. **日志清理**
```php
$sanitized = WebhookSecurityHelper::sanitizePayloadForLogging($payload);
Log::info('Webhook received', $sanitized);
```

---

### 8. 租户隔离增强 (High)

**问题**: 查询时可能跨租户访问数据
**修复**: 模型全局作用域

**实现位置**:
- `src/Models/Payment.php:22-45`
- `src/Models/Subscription.php:20-43`

**自动隔离**:
```php
// 自动过滤当前租户数据
Payment::all(); // 自动添加 WHERE tenant_id = ?

// 管理员查看所有租户
Payment::allTenants()->get();
```

**自动设置tenant_id**:
```php
// 创建时自动设置
$payment = Payment::create([...]); // tenant_id自动填充
```

---

### 9. 日志敏感信息清理 (Medium)

**实现位置**:
- `src/Helpers/SecureLogger.php`

**使用方式**:
```php
use App\Helpers\SecureLogger;

// 替代 Log::info()
SecureLogger::info('User data', [
    'user' => $user,
    'api_key' => 'sk_live_xxx', // 自动替换为 [REDACTED]
]);

// 或手动清理
$sanitized = SecureLogger::sanitize($data);
Log::info('Data', $sanitized);
```

**自动清理的字段**:
- `api_key`, `secret_key`, `password`, `token`
- `card_number`, `cvv`, `ssn`
- `authorization`, `webhook_secret`

---

## 🚀 生产环境部署清单

### 必须配置

- [ ] **加密现有API密钥**
```bash
php artisan tinker
>>> $key = encrypt('sk_live_your_stripe_key');
>>> DB::table('general_settings')->update(['more_configs' => ...]);
```

- [ ] **注册安全中间件**
```php
// app/Http/Kernel.php
protected $middleware = [
    \App\Http\Middleware\ForceHttps::class,
    \App\Http\Middleware\SecurityHeaders::class,
];
```

- [ ] **配置Stripe Webhook IP (可选)**
```php
// 在 PaymentController::webhook() 中添加
use App\Helpers\WebhookSecurityHelper;

public function webhook(Request $request) {
    if (!WebhookSecurityHelper::isValidStripeIp($request->ip())) {
        Log::warning('Webhook from invalid IP', ['ip' => $request->ip()]);
        return response()->json(['error' => 'Forbidden'], 403);
    }
    // ... 继续处理
}
```

- [ ] **设置环境变量**
```env
APP_ENV=production
APP_DEBUG=false
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
```

### 推荐配置

- [ ] **启用日志监控**
- [ ] **配置备份策略**
- [ ] **设置错误追踪** (Sentry/Bugsnag)
- [ ] **实施双因素认证** (未包含在此修复中)

---

## 🔍 OWASP Top 10 对照

| 风险 | 状态 | 修复措施 |
|-----|------|---------|
| A01 - Broken Access Control | ✅ | 租户隔离全局作用域, Webhook IP验证 |
| A02 - Cryptographic Failures | ✅ | API密钥加密存储 |
| A03 - Injection | ✅ | FormRequest验证, 输入清理 |
| A04 - Insecure Design | ✅ | 支付幂等性, 速率限制 |
| A05 - Security Misconfiguration | ✅ | 安全响应头, HTTPS强制 |
| A07 - Authentication Failures | ⚠️  | 已有Filament认证 (推荐添加2FA) |
| A08 - Data Integrity Failures | ✅ | Webhook签名验证 |
| A09 - Logging Failures | ✅ | SecureLogger清理敏感信息 |

---

## 📚 使用示例

### 支付处理
```php
// 控制器自动使用PaymentSuccessRequest验证
public function success(PaymentSuccessRequest $request) {
    $validated = $request->validated();
    // 自动清理的session_id
    $sessionId = $validated['session_id'];

    // 数据库事务自动处理幂等性
    DB::transaction(function () use ($sessionId) {
        // ...
    });
}
```

### 日志记录
```php
use App\Helpers\SecureLogger;

// 自动清理敏感信息
SecureLogger::info('Processing payment', [
    'session_data' => $sessionData, // 自动清理card_number等
]);
```

### 模型查询
```php
// 自动隔离租户
$payments = Payment::where('status', 'completed')->get();
// 等同于: WHERE tenant_id = ? AND status = 'completed'

// 管理员查看所有租户
$allPayments = Payment::allTenants()->get();
```

---

## ⚠️ 重要提示

1. **迁移现有数据**: 如果已有未加密的API密钥，需要手动加密
2. **测试环境**: 在开发/测试环境，某些功能会自动禁用(如HTTPS强制)
3. **性能影响**: 加密/解密会有轻微性能影响，但安全优先
4. **日志大小**: 清理敏感信息后日志会更安全，但调试时需注意

---

## 🔧 故障排除

### API密钥解密失败
```
Failed to decrypt setting - may be unencrypted legacy data
```
**解决**: 旧数据未加密，需要手动加密或允许降级(当前代码已支持)

### 速率限制触发
```
Too Many Attempts
```
**解决**: 调整 `routes/tenant.php` 中的throttle参数

### CSP策略阻止资源
**解决**: 在 `SecurityHeaders.php` 中添加允许的域名

---

## 📞 支持

如有安全问题，请联系安全团队或创建私密issue。

**切勿在公开渠道讨论安全漏洞！**
