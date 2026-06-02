<?php

namespace App\Services;

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use PhpXmlRpc\Encoder;
use RuntimeException;

/**
 * Odoo 17 External API client
 *
 * يدعم وضعين:
 *  1. الوضع الافتراضي: يستخدم credentials من config (للـ background jobs والـ sync)
 *  2. وضع المستخدم: setCredentials() لاستخدام بيانات مستخدم معيّن (للـ login والعمليات المرتبطة بالمستخدم)
 */
class OdooService
{
    protected string $url;
    protected string $db;
    protected string $username;
    protected string $password; // أو API key
    protected ?int $uid = null;
    protected Encoder $encoder;

    public function __construct()
    {
        $this->url      = rtrim(config('odoo.url'), '/');
        $this->db       = config('odoo.db');
        $this->username = config('odoo.username');
        $this->password = config('odoo.api_key');
        $this->encoder  = new Encoder();
    }

    /**
     * استخدام بيانات اعتماد مختلفة (مثلاً بيانات المستخدم اللي مسجّل دخول)
     */
    public function setCredentials(string $username, string $password, ?int $uid = null): self
    {
        $this->username = $username;
        $this->password = $password;
        $this->uid = $uid;
        return $this;
    }

    /**
     * الرجوع إلى حساب الخدمة (المسؤول) المُعرَّف في الإعدادات.
     * يُستخدم للعمليات التي تتطلب صلاحيات أعلى من صلاحيات الموظف العادي،
     * مثل إنشاء/تعديل سجلات الحضور (hr.attendance) — الموظفون العاديون
     * لا يملكون صلاحية إنشائها في Odoo.
     */
    public function useServiceAccount(): self
    {
        $this->username = config('odoo.username');
        $this->password = config('odoo.api_key');
        $this->uid = null; // إعادة المصادقة كحساب الخدمة
        return $this;
    }

    /**
     * تسجيل دخول والحصول على uid - يرجّع false لو فشل (بدون رمي exception)
     */
    public function tryAuthenticate(string $username, string $password): int|false
    {
        $client = new Client("{$this->url}/xmlrpc/2/common");
        $client->setSSLVerifyPeer(config('odoo.verify_ssl', true));

        $request = new Request('authenticate', [
            new Value($this->db, 'string'),
            new Value($username, 'string'),
            new Value($password, 'string'),
            new Value([], 'struct'),
        ]);

        $response = $client->send($request);

        if ($response->faultCode()) {
            return false;
        }

        $uid = $this->encoder->decode($response->value());

        return $uid ? (int) $uid : false;
    }

    public function authenticate(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $uid = $this->tryAuthenticate($this->username, $this->password);

        if (!$uid) {
            throw new RuntimeException('فشل تسجيل الدخول إلى Odoo. تأكد من البيانات.');
        }

        return $this->uid = $uid;
    }

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = [])
    {
        $uid = $this->authenticate();

        $client = new Client("{$this->url}/xmlrpc/2/object");
        $client->setSSLVerifyPeer(config('odoo.verify_ssl', true));

        $request = new Request('execute_kw', [
            new Value($this->db, 'string'),
            new Value($uid, 'int'),
            new Value($this->password, 'string'),
            new Value($model, 'string'),
            new Value($method, 'string'),
            $this->encoder->encode($args),
            $this->encoder->encode($kwargs),
        ]);

        $response = $client->send($request);

        if ($response->faultCode()) {
            throw new RuntimeException("خطأ في {$model}.{$method}: " . $response->faultString());
        }

        return $this->encoder->decode($response->value());
    }

    // ==== Convenience methods ====

    public function search(string $model, array $domain = [], array $kwargs = []): array
    {
        return $this->executeKw($model, 'search', [$domain], $kwargs);
    }

    public function searchCount(string $model, array $domain = []): int
    {
        return (int) $this->executeKw($model, 'search_count', [$domain]);
    }

    public function read(string $model, array $ids, array $fields = []): array
    {
        return $this->executeKw($model, 'read', [$ids], $fields ? ['fields' => $fields] : []);
    }

    public function searchRead(string $model, array $domain = [], array $fields = [],
                               int $limit = 0, int $offset = 0, string $order = ''): array
    {
        $kwargs = [];
        if ($fields) $kwargs['fields'] = $fields;
        if ($limit)  $kwargs['limit']  = $limit;
        if ($offset) $kwargs['offset'] = $offset;
        if ($order)  $kwargs['order']  = $order;

        return $this->executeKw($model, 'search_read', [$domain], $kwargs);
    }

    public function create(string $model, array $values): int
    {
        return (int) $this->executeKw($model, 'create', [$values]);
    }

    public function write(string $model, array $ids, array $values): bool
    {
        return (bool) $this->executeKw($model, 'write', [$ids, $values]);
    }

    public function unlink(string $model, array $ids): bool
    {
        return (bool) $this->executeKw($model, 'unlink', [$ids]);
    }

    /**
     * مساعد: استخراج id من many2one field (يجي بصيغة [id, "name"] أو false)
     */
    public static function many2oneId(mixed $value): ?int
    {
        return is_array($value) && isset($value[0]) ? (int) $value[0] : null;
    }

    public static function many2oneName(mixed $value): ?string
    {
        return is_array($value) && isset($value[1]) ? (string) $value[1] : null;
    }
}
