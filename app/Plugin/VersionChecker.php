<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * 语义化版本约束检查器.
 *
 * 支持约束格式: >=1.0.0, ~1.2, ^2.0, 1.*, >=1.0 <2.0
 */
final class VersionChecker
{
    /**
     * 检查版本是否满足约束.
     */
    public function satisfies(string $version, string $constraint): bool
    {
        $version = ltrim($version, 'vV');
        $constraint = trim($constraint);

        // 精确匹配
        if ($version === $constraint) {
            return true;
        }

        // 处理 || 运算符
        if (str_contains($constraint, '||')) {
            $parts = array_map('trim', explode('||', $constraint));
            foreach ($parts as $part) {
                if ($this->satisfies($version, $part)) {
                    return true;
                }
            }
            return false;
        }

        // 处理空格分隔的多约束（AND 关系）
        if (preg_match('/\s/', $constraint)) {
            $parts = preg_split('/\s+/', $constraint);
            foreach ($parts as $part) {
                if (! $this->satisfiesSingle($version, $part)) {
                    return false;
                }
            }
            return true;
        }

        return $this->satisfiesSingle($version, $constraint);
    }

    private function satisfiesSingle(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        // >=1.0.0, <=2.0.0, >1.0.0, <2.0.0
        if (preg_match('/^(>=|<=|>|<)(\d+\.\d+\.\d+)$/', $constraint, $m)) {
            return $this->compare($version, $m[1], $m[2]);
        }

        // >=1.0 (补全为 >=1.0.0)
        if (preg_match('/^(>=|<=|>|<)(\d+\.\d+)$/', $constraint, $m)) {
            return $this->compare($version, $m[1], $m[2] . '.0');
        }

        // ^2.0.0 -> >=2.0.0 <3.0.0
        if (preg_match('/^\^(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $lower = "{$m[1]}.{$m[2]}.{$m[3]}";
            if ((int) $m[1] === 0) {
                $upper = "0." . ((int) $m[2] + 1) . '.0';
            } else {
                $upper = ((int) $m[1] + 1) . '.0.0';
            }
            return $this->compare($version, '>=', $lower)
                && $this->compare($version, '<', $upper);
        }

        // ^2.0 -> >=2.0.0 <3.0.0
        if (preg_match('/^\^(\d+)\.(\d+)$/', $constraint, $m)) {
            return $this->satisfiesSingle($version, "^{$m[1]}.{$m[2]}.0");
        }

        // ~1.2.3 -> >=1.2.3 <1.3.0
        if (preg_match('/^~(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $lower = "{$m[1]}.{$m[2]}.{$m[3]}";
            $upper = "{$m[1]}." . ((int) $m[2] + 1) . '.0';
            return $this->compare($version, '>=', $lower)
                && $this->compare($version, '<', $upper);
        }

        // ~1.2 -> >=1.2.0 <2.0.0
        if (preg_match('/^~(\d+)\.(\d+)$/', $constraint, $m)) {
            $lower = "{$m[1]}.{$m[2]}.0";
            $upper = ((int) $m[1] + 1) . '.0.0';
            return $this->compare($version, '>=', $lower)
                && $this->compare($version, '<', $upper);
        }

        // 1.* -> >=1.0.0 <2.0.0
        if (preg_match('/^(\d+)\.\*$/', $constraint, $m)) {
            $lower = "{$m[1]}.0.0";
            $upper = ((int) $m[1] + 1) . '.0.0';
            return $this->compare($version, '>=', $lower)
                && $this->compare($version, '<', $upper);
        }

        // 1.2.* -> >=1.2.0 <1.3.0
        if (preg_match('/^(\d+)\.(\d+)\.\*$/', $constraint, $m)) {
            $lower = "{$m[1]}.{$m[2]}.0";
            $upper = "{$m[1]}." . ((int) $m[2] + 1) . '.0';
            return $this->compare($version, '>=', $lower)
                && $this->compare($version, '<', $upper);
        }

        // 裸版本号，宽松匹配
        if (preg_match('/^\d+\.\d+\.\d+$/', $constraint)) {
            return $this->compare($version, '>=', $constraint);
        }

        return false;
    }

    private function compare(string $v1, string $op, string $v2): bool
    {
        $result = version_compare($v1, $v2);

        return match ($op) {
            '>=' => $result >= 0,
            '<=' => $result <= 0,
            '>' => $result > 0,
            '<' => $result < 0,
            default => false,
        };
    }
}
