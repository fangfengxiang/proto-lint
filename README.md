# proto-lint

[![CI](https://github.com/fangfengxiang/proto-lint/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/fangfengxiang/proto-lint/actions/workflows/ci.yml)
[![Static Analysis](https://github.com/fangfengxiang/proto-lint/actions/workflows/static-analysis.yml/badge.svg?branch=main)](https://github.com/fangfengxiang/proto-lint/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://www.apache.org/licenses/LICENSE-2.0)
[![Latest Release](https://img.shields.io/github/v/release/fangfengxiang/proto-lint)](https://github.com/fangfengxiang/proto-lint/releases)

> Schema-First PHP 协议治理与一致性 Lint 引擎

`proto-lint` 是一个 PHP CLI 工具，充当 `.proto` 契约与 PHP 源码之间的静态一致性校验引擎。它通过三套命令实现协议治理闭环：`check`（契约一致性检查）、`shadow-lint`（影子流量审计）、`inject-attributes`（注解安全注入）。

---

## 目录

- [特性](#特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [配置](#配置)
  - [proto-bulk.json](#proto-bulkjson)
  - [proto-mapping.json](#proto-mappingjson)
- [命令](#命令)
  - [check — 契约一致性检查](#check--契约一致性检查)
  - [shadow-lint — 影子流量审计](#shadow-lint--影子流量审计)
  - [inject-attributes — 注解安全注入](#inject-attributes--注解安全注入)
- [三阶段校验管线](#三阶段校验管线)
- [CI/CD 集成](#cicd-集成)
- [测试](#测试)
- [开发](#开发)
- [变更日志](#变更日志)
- [贡献](#贡献)
- [安全](#安全)
- [License](#license)

---

## 特性

- **Schema-First 契约对齐** — 以 `.proto` 为唯一事实来源，校验 PHP 形参位置、类型、序号三重一致性
- **影子流量审计** — JSON 载荷 × PHP DTO × `.proto` 三方交集检测，发现字段缺损与类型漂移
- **安全注解注入** — FormatPreservingPrinter 保留原始 token，PHP 8 Attribute 与 PHP 7 定界符沙箱双模式
- **命名空间冲突隔离** — 自动前缀 `{ClassName}_{MethodName}_{KeyPath}` 或显式 FQCN 双策略
- **可配置严格度** — `rule_overrides` 按服务/方法粒度降级 Rule-03 从 FAIL 到 Warning
- **protoc 原生管线** — `protoc --descriptor_set_out` → `google/protobuf` 运行时反序列化，零自定义解析器

## 安装

### 全局安装

```bash
composer global require proto-lint/proto-lint
```

### 项目安装

```bash
composer require --dev proto-lint/proto-lint
```

### 源码安装

```bash
git clone https://github.com/fangfengxiang/proto-lint.git
cd proto-lint
composer install
```

### 依赖

- **PHP** >= 8.2
- **[protoc](https://github.com/protocolbuffers/protobuf)** 编译器
  - macOS: `brew install protobuf`
  - Ubuntu: `sudo apt install protobuf-compiler`
- **Composer 依赖**: `nikic/php-parser ^5.0`、`google/protobuf ^5.29`、`symfony/console ^7.0`

## 快速开始

```bash
# 1. 编写 .proto 契约
cat > proto/user_service.proto << 'EOF'
syntax = "proto3";
package demo;
message GetUserRequest { int32 user_id = 1; string name = 2; }
service UserService { rpc GetUser(GetUserRequest) returns (GetUserResponse); }
EOF

# 2. 声明治理范围
cat > proto-bulk.json << 'EOF'
{
    "source_dir": "src/Service/",
    "default_target_proto": "proto/user_service.proto",
    "services": { "UserService": { "methods": { "getUser": {} } } }
}
EOF

# 3. 检查契约一致性
./bin/proto-lint check --config="proto-bulk.json"
```

## 配置

### proto-bulk.json

批量扫描声明，定义整个项目的治理范围：

```json
{
    "$schema": "https://openspec.dev/proto-lint/v1/bulk.json",
    "source_dir": "src/Service/",
    "default_target_proto": "proto/global_service.proto",
    "services": {
        "UserService": {
            "methods": {
                "getUser": {},
                "updateUser": {
                    "mapping_file": "proto/mappings/user_update.json",
                    "target_proto_files": ["proto/user_write.proto"]
                }
            }
        }
    }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `source_dir` | string | PHP 源码根目录 |
| `default_target_proto` | string | 默认 `.proto` 文件路径 |
| `services` | object | 服务 → 方法映射 |
| `methods.*.mapping_file` | string? | 单方法影子流量配置文件 |
| `methods.*.target_proto_files` | string[]? | 方法级 `.proto` 覆盖 |
| `methods.*.rule_overrides` | object? | 方法级规则覆盖（`{"rule_03": "warning"}`） |

### proto-mapping.json

单体函数的影子流量与映射配置：

```json
{
    "$schema": "https://openspec.dev/proto-lint/v1/mapping.json",
    "target_proto_files": ["proto/user_service.proto"],
    "request": {
        "payload": {
            "user_id": 10001,
            "name": "alice",
            "data": { "id": 10001, "name": "alice" }
        },
        "field_class_mappings": {
            "data": "App\\Protocol\\Message\\UserMessage"
        }
    },
    "response": {
        "payload": { "success": true },
        "field_class_mappings": {}
    }
}
```

## 命令

### check — 契约一致性检查

```bash
./bin/proto-lint check --config="proto-bulk.json"
```

输出分级日志（INFO / OK / ERROR / FATAL），检测到错误时退出码为 1。

### shadow-lint — 影子流量审计

```bash
./bin/proto-lint shadow-lint --config="proto-mapping.json"
```

审计 JSON 载荷字段是否全部有 PHP DTO 映射，检测字段缺损风险。同时审计请求和响应载荷。

### inject-attributes — 注解安全注入

```bash
# PHP 8+ 模式（Attribute 注入）
./bin/proto-lint inject-attributes --config="proto-bulk.json"

# PHP 7 模式（定界符沙箱）
./bin/proto-lint inject-attributes --config="proto-bulk.json" --php7

# 预览模式（不写入文件）
./bin/proto-lint inject-attributes --config="proto-bulk.json" --dry-run
```

### 全局选项

| 选项 | 说明 |
|------|------|
| `--version` | 输出版本号 |
| `--help` | 列出可用命令 |
| `--verbose` | 调试输出 |

## 三阶段校验管线

| 规则 | 描述 |
|------|------|
| Rule-01 | 位置参数绝对对齐：PHP 形参物理位置索引与 `#[ProtoField(X)]` 的 X 严格一致，且与 .proto 字段顺序 100% 重合 |
| Rule-02 | 深度级联递归：遇到复合类型时递归校验每个 DTO 属性的 `#[ProtoField]` 注解完整性与序号匹配 |
| Rule-03 | 强类型确定性：检测 `mixed`、联合类型、空泛型 `array`，默认 FAIL，可通过 `rule_overrides` 降级为 Warning |

## CI/CD 集成

### GitHub Actions

```yaml
- name: Install protoc
  run: sudo apt install -y protobuf-compiler

- name: Install dependencies
  run: composer install --no-interaction

- name: Run proto-lint check
  run: ./bin/proto-lint check --config="proto-bulk.json"
```

退出码 0 = 通过（含 Warning 但无 FAIL），1 = 检测到错误。

### Composer Scripts

```bash
composer test           # 运行全部测试
composer cs:check       # 代码风格检查
composer cs:fix         # 自动修复代码风格
composer stan           # PHPStan 静态分析
composer check-all      # 运行所有检查
```

## 测试

```bash
composer test              # 全部测试
composer test:unit         # 单元测试
composer test:integration  # 集成测试
composer test:coverage     # 覆盖率报告
```

## 开发

详见 [CONTRIBUTING.md](CONTRIBUTING.md)。

```bash
# 克隆并安装
git clone https://github.com/fangfengxiang/proto-lint.git
cd proto-lint
composer install

# 运行所有检查
composer check-all
```

## 变更日志

详见 [CHANGELOG.md](CHANGELOG.md)。

## 贡献

欢迎提交 PR 和 Issue！请先阅读 [CONTRIBUTING.md](CONTRIBUTING.md)。

如发现安全漏洞，请按 [SECURITY.md](SECURITY.md) 流程私下报告。

## License

[Apache-2.0](LICENSE) © proto-lint
