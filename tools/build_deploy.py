#!/usr/bin/env python3
"""Сборка деплоя ooofix-xmlupd-cloud из ядра ooofix.xmlupd + упаковка ZIP."""

from __future__ import annotations

import re
import shutil
import zipfile
from pathlib import Path

CLOUD_ROOT = Path(__file__).resolve().parent.parent
BOX_ROOT = CLOUD_ROOT.parent / "ooofix.xmlupd"
DIST_DIR = CLOUD_ROOT.parent / "dist"
ZIP_NAME = "ooofix-xmlupd-cloud-deploy.zip"

# PHP: lib/... -> src/Core/...
SYNC_PHP = [
    "lib/ModuleInfo.php",
    "lib/Documents/Upd/UpdXmlWriter.php",
    "lib/Documents/Upd/UpdMapper.php",
    "lib/Documents/Upd/UpdValidator.php",
    "lib/Documents/Upd/UpdBuilder.php",
    "lib/Xml/WriterBuffer.php",
    "lib/Xml/XmlFormatter.php",
    "lib/Xml/XsdErrorFormatter.php",
    "lib/Xml/XmlValidationException.php",
    "lib/Xml/XsdSchemaRegistry.php",
    "lib/Crm/ProductAmountCalculator.php",
    "lib/Crm/ProductPriceNormalizer.php",
    "lib/Crm/ProductTotalsReconciler.php",
    "lib/Crm/DocumentTotalsCalculator.php",
    "lib/Address/AddressComponentParser.php",
    "lib/Address/RegionCodeResolver.php",
    "lib/Address/RequisiteAddressResolver.php",
    "lib/Contract/ConfigInterface.php",
    "lib/Contract/CrmAdapterInterface.php",
    "lib/Contract/DocumentBuilderInterface.php",
    "lib/Contract/EdoGatewayInterface.php",
    "lib/Contract/GenerateResultInterface.php",
    "lib/Contract/GenerateRuntimeInterface.php",
    "lib/Dto/DocumentRecordDto.php",
    "lib/Dto/EntityContextDto.php",
    "lib/Dto/GenerateRequestDto.php",
    "lib/Dto/GenerateResultDto.php",
    "lib/Person/FioParser.php",
    "lib/ValidationMessages.php",
    "lib/XmlEncoder.php",
    "lib/XmlValidator.php",
    "lib/DocumentStatus.php",
]

SYNC_BINARY = [
    ("config/mapping/upd.php", "src/Core/config/mapping/upd.php"),
]

EXCLUDE_FROM_ZIP = {
    ".env",
    ".git",
    ".idea",
    ".vscode",
    "dist",
    "tools/build_deploy.py",
}

EXCLUDE_DIR_NAMES = {".git", ".idea", ".vscode", "dist"}


def box_to_core_path(rel: str) -> Path:
    if rel.startswith("lib/"):
        return CLOUD_ROOT / "src/Core" / rel[4:]
    return CLOUD_ROOT / rel


def transform_php(content: str) -> str:
    content = content.replace("namespace Ooofix\\Xmlupd\\", "namespace Ooofix\\XmlupdCloud\\Core\\")
    content = content.replace("namespace Ooofix\\Xmlupd;", "namespace Ooofix\\XmlupdCloud\\Core;")
    content = content.replace("use Ooofix\\Xmlupd\\", "use Ooofix\\XmlupdCloud\\Core\\")
    content = content.replace("\\Ooofix\\Xmlupd\\", "\\Ooofix\\XmlupdCloud\\Core\\")
    content = content.replace(
        "dirname(__DIR__, 2) . '/config/schemas'",
        "dirname(__DIR__) . '/config/schemas'",
    )
    content = content.replace(
        "DataCollector::TYPE_SMART_INVOICE",
        "'smart_invoice'",
    )
    if "declare(strict_types=1);" not in content:
        content = content.replace("<?php\n", "<?php\n\ndeclare(strict_types=1);\n", 1)
    return content


def transform_xsd_schema_registry(content: str) -> str:
    content = transform_php(content)
    content = content.replace(
        """    private static function schemasRoot(): string
    {
        return dirname(__DIR__) . '/config/schemas';
    }""",
        """    private static function schemasRoot(): string
    {
        if (defined('OOOFIX_CLOUD_ROOT')) {
            return OOOFIX_CLOUD_ROOT . '/src/Core/config/schemas';
        }

        return dirname(__DIR__) . '/config/schemas';
    }""",
    )
    content = content.replace(
        "'Файлы XSD для версии «' . $version . '» отсутствуют в config/schemas/.'",
        "'Файлы XSD для версии «' . $version . '» отсутствуют в ' . self::schemasRoot() . '/'",
    )
    return content


def transform_module_info(content: str) -> str:
    content = transform_php(content)
    content = content.replace(
        "public const MODULE_ID = 'ooofix.xmlupd';",
        "public const MODULE_ID = 'ooofix-xmlupd-cloud';",
    )
    content = content.replace(
        """        $arModuleVersion = [];
        $path = dirname(__DIR__) . '/install/version.php';
        if (is_file($path)) {
            include $path;
        }

        $version = (string)($arModuleVersion['VERSION'] ?? '1.0.0');""",
        """        $root = defined('OOOFIX_CLOUD_ROOT') ? OOOFIX_CLOUD_ROOT : dirname(__DIR__, 2);
        $versionFile = $root . '/VERSION';
        if (is_file($versionFile)) {
            $lines = file($versionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $version = is_array($lines) && isset($lines[0]) ? trim($lines[0]) : '1.0.0';
        } else {
            $version = '1.0.0';
        }""",
    )
    return content


def read_box_version() -> tuple[str, str]:
    version_file = BOX_ROOT / "install/version.php"
    text = version_file.read_text(encoding="utf-8")
    ver = re.search(r'"VERSION"\s*=>\s*"([^"]+)"', text)
    date = re.search(r'"VERSION_DATE"\s*=>\s*"([^"]+)"', text)
    return (
        ver.group(1) if ver else "0.0.0",
        date.group(1) if date else "",
    )


def sync_core() -> int:
    if not BOX_ROOT.is_dir():
        raise SystemExit(f"Box module not found: {BOX_ROOT}")

    count = 0
    for rel in SYNC_PHP:
        src = BOX_ROOT / rel
        dst = box_to_core_path(rel)
        if not src.is_file():
            print(f"SKIP missing: {rel}")
            continue
        dst.parent.mkdir(parents=True, exist_ok=True)
        raw = src.read_text(encoding="utf-8")
        if rel == "lib/ModuleInfo.php":
            text = transform_module_info(raw)
        elif rel == "lib/Xml/XsdSchemaRegistry.php":
            text = transform_xsd_schema_registry(raw)
        else:
            text = transform_php(raw)
        dst.write_text(text, encoding="utf-8", newline="\n")
        count += 1

    for src_rel, dst_rel in SYNC_BINARY:
        src = BOX_ROOT / src_rel
        dst = CLOUD_ROOT / dst_rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)
        count += 1

    schemas_src = BOX_ROOT / "config/schemas"
    schemas_dst = CLOUD_ROOT / "src/Core/config/schemas"
    if schemas_src.is_dir():
        if schemas_dst.exists():
            shutil.rmtree(schemas_dst)
        shutil.copytree(schemas_src, schemas_dst)
        count += sum(1 for _ in schemas_dst.rglob("*") if _.is_file())

    return count


def sync_frontend() -> int:
    src = CLOUD_ROOT / "frontend"
    dst = CLOUD_ROOT / "public/frontend"
    if not src.is_dir():
        return 0
    if dst.exists():
        shutil.rmtree(dst)
    shutil.copytree(src, dst)
    return sum(1 for _ in dst.rglob("*") if _.is_file())


def sync_assets() -> int:
    src = CLOUD_ROOT / "assets"
    dst = CLOUD_ROOT / "public/assets"
    if not src.is_dir():
        return 0
    dst.mkdir(parents=True, exist_ok=True)
    count = 0
    for file_path in src.iterdir():
        if not file_path.is_file():
            continue
        shutil.copy2(file_path, dst / file_path.name)
        count += 1
    return count


def write_version_file(version: str, version_date: str) -> None:
    (CLOUD_ROOT / "VERSION").write_text(
        f"{version}\n{version_date}\nbox=ooofix.xmlupd\n",
        encoding="utf-8",
    )


def should_zip(path: Path, root: Path) -> bool:
    rel = path.relative_to(root).as_posix()
    if rel in EXCLUDE_FROM_ZIP:
        return False
    if path.name in EXCLUDE_FROM_ZIP:
        return False
    for part in path.parts:
        if part in EXCLUDE_DIR_NAMES:
            return False
    # Дубликат UI — в архиве только public/frontend
    if rel.startswith("frontend/"):
        return False
    return True


def build_zip() -> Path:
    DIST_DIR.mkdir(parents=True, exist_ok=True)
    zip_path = DIST_DIR / ZIP_NAME
    if zip_path.exists():
        zip_path.unlink()

    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for file_path in sorted(CLOUD_ROOT.rglob("*")):
            if not file_path.is_file():
                continue
            if not should_zip(file_path, CLOUD_ROOT):
                continue
            arcname = f"ooofix-xmlupd-cloud/{file_path.relative_to(CLOUD_ROOT).as_posix()}"
            zf.write(file_path, arcname)

    return zip_path


def main() -> None:
    version, version_date = read_box_version()
    synced = sync_core()
    ui_files = sync_frontend()
    asset_files = sync_assets()
    write_version_file(version, version_date)
    zip_path = build_zip()

    print(f"Box module: {BOX_ROOT}")
    print(f"Core synced files: {synced}")
    print(f"Frontend -> public/frontend: {ui_files} files")
    print(f"Assets -> public/assets: {asset_files} files")
    print(f"Version: {version} ({version_date})")
    print(f"Deploy ZIP: {zip_path}")
    print(f"ZIP size MB: {round(zip_path.stat().st_size / 1024 / 1024, 2)}")
    print()
    print("Upload to server:")
    print("  /home/b/btops/up-fix/public_html/market/ooofix-xmlupd-cloud/")
    print("Then: cp .env.example .env && fill secrets && run install_btops_app.sql")


if __name__ == "__main__":
    main()
