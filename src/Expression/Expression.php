<?php

declare(strict_types=1);

namespace Marshal\Database\Expression;

class Expression
{
    public const string EXPR_EQ = "eq";
    public const string EXPR_GT = "gt";
    public const string EXPR_GTE = "gte";
    public const string EXPR_IN = "in";
    public const string EXPR_ISNOTNULL = "isNotNull";
    public const string EXPR_ISNULL = "isNull";
    public const string EXPR_LT = "lt";
    public const string EXPR_LTE = "lte";
    public const string EXPR_NOTIN = "notIn";
    public const string EXPR_OR = "or";
    public const string EXPR_RAW = "raw";

    public function __construct(private string $type)
    {
        if (! \in_array($type, $this->getAllowedTypes(), true)) {
            throw new \InvalidArgumentException("Expression type $type not allowed");
        }
    }

    public function getAllowedTypes(): array
    {
        return [
            self::EXPR_EQ,
            self::EXPR_GT,
            self::EXPR_GTE,
            self::EXPR_IN,
            self::EXPR_ISNOTNULL,
            self::EXPR_ISNULL,
            self::EXPR_LT,
            self::EXPR_LTE,
            self::EXPR_NOTIN,
            self::EXPR_OR,
            self::EXPR_RAW,
        ];
    }
}
