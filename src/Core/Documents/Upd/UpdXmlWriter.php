<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Documents\Upd;

use Ooofix\XmlupdCloud\Core\ModuleInfo;
use Ooofix\XmlupdCloud\Core\Address\AddressComponentParser;
use Ooofix\XmlupdCloud\Core\Xml\WriterBuffer;

/**
 * Генерация XML УПД по образцу Diadoc (формат ФНС 5.03).
 * Структура сверена с edo_6608_2026-06-15_07-29 (2).xml
 */
class UpdXmlWriter
{
    private const DOC_NAME = 'Документ об отгрузке товаров (выполнении работ), передаче имущественных прав (документ об оказании услуг)';

    /** @param array<string, mixed> $m */
    public function build(array $m): string
    {
        $w = new WriterBuffer();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement('Файл');
        $w->writeAttribute('ИдФайл', $this->buildFileId($m));
        $w->writeAttribute('ВерсФорм', \Ooofix\XmlupdCloud\Core\Config::xmlFormatVersion());
        $w->writeAttribute('ВерсПрог', ModuleInfo::programName());

        $w->startElement('Документ');
        $w->writeAttribute('КНД', '1115131');
        $w->writeAttribute('ВремИнфПр', date('H.i.s'));
        $w->writeAttribute('ДатаИнфПр', date('d.m.Y'));
        $w->writeAttribute('НаимЭконСубСост', (string)($m['seller_display_name'] ?? $m['seller_name'] ?? ''));
        $w->writeAttribute('Функция', (string)($m['doc_function'] ?? 'СЧФДОП'));
        $w->writeAttribute('ПоФактХЖ', self::DOC_NAME);
        $w->writeAttribute('НаимДокОпр', self::DOC_NAME);

        $this->writeSvSchFakt($w, $m);
        $this->writeProductsTable($w, $m);
        $this->writeSvProdPer($w, $m);
        $this->writeSignatory($w, $m);

        $w->endElement(); // Документ
        $w->endElement(); // Файл

        return $w->outputMemory();
    }

    /** @param array<string, mixed> $m */
    private function buildFileId(array $m): string
    {
        $buyerPart = $this->buildOperatorSegment($m, 'buyer');
        $sellerPart = $this->buildOperatorSegment($m, 'seller');

        return sprintf(
            'ON_NSCHFDOPPR_%s_%s_%s_%s_0_0_0_0_0_00',
            $buyerPart,
            $sellerPart,
            date('Ymd'),
            $this->uuid()
        );
    }

    /**
     * Сегмент ИдФайл в формате, близком к выгрузке Diadoc (см. ON_NSCHFDOPPR_*.xml в модуле).
     * @param array<string, mixed> $m
     */
    private function buildOperatorSegment(array $m, string $role): string
    {
        $inn = preg_replace('/\D/', '', (string)($m[$role . '_inn'] ?? '0'));
        if ($inn === '') {
            $inn = '0';
        }

        $kpp = preg_replace('/\D/', '', (string)($m[$role . '_kpp'] ?? ''));
        $ogrn = preg_replace('/\D/', '', (string)($m[$role . '_ogrn'] ?? ''));
        $isIp = (int)($m[$role . '_is_ip'] ?? 0) === 1 || strlen($inn) === 12;

        if ($isIp) {
            $tail = $ogrn !== ''
                ? str_pad($ogrn, 15, '0', STR_PAD_RIGHT)
                : date('YmdHis') . '000000000';

            return '2BM-' . $inn . '-' . $tail . '0000000';
        }

        $kppPart = $kpp !== '' ? $kpp : '0';
        $suffix = date('YmdHis') . str_pad((string)random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        return '2BM-' . $inn . '-' . $kppPart . '-' . $suffix;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** @param array<string, mixed> $m */
    private function writeSvSchFakt(WriterBuffer $w, array $m): void
    {
        $w->startElement('СвСчФакт');
        $w->writeAttribute('НомерДок', (string)$m['doc_number']);
        $w->writeAttribute('ДатаДок', (string)$m['doc_date']);

        $this->writeParticipant($w, 'СвПрод', $m, 'seller');
        $this->writeGruzOt($w);
        $this->writeParticipant($w, 'ГрузПолуч', $m, 'buyer');
        $this->writeParticipant($w, 'СвПокуп', $m, 'buyer');

        $w->startElement('ДенИзм');
        $w->writeAttribute('КодОКВ', '643');
        $w->writeAttribute('НаимОКВ', 'Российский рубль');
        $w->endElement();

        $w->endElement();
    }

    private function writeGruzOt(WriterBuffer $w): void
    {
        $w->startElement('ГрузОт');
        $w->startElement('ОнЖе');
        $w->text('он же');
        $w->endElement();
        $w->endElement();
    }

    /**
     * @param array<string, mixed> $m
     * @param 'seller'|'buyer' $role
     */
    private function writeParticipant(WriterBuffer $w, string $tag, array $m, string $role): void
    {
        $inn = (string)($m[$role . '_inn'] ?? '');
        $isIp = (int)($m[$role . '_is_ip'] ?? 0) === 1 || strlen(preg_replace('/\D/', '', $inn)) === 12;

        $w->startElement($tag);
        $w->startElement('ИдСв');

        if ($isIp) {
            $w->startElement('СвИП');
            $w->writeAttribute('ИННФЛ', $inn);
            $this->writeFio($w, $m, $role);
            $w->endElement();
        } else {
            $w->startElement('СвЮЛУч');
            $w->writeAttribute('НаимОрг', (string)($m[$role . '_name'] ?? ''));
            $w->writeAttribute('ИННЮЛ', $inn);
            $kpp = (string)($m[$role . '_kpp'] ?? '');
            if ($kpp !== '') {
                $w->writeAttribute('КПП', $kpp);
            }
            $w->endElement();
        }

        $w->endElement(); // ИдСв
        $this->writeAddressRf($w, $m, $role);
        $w->endElement();
    }

    /**
     * @param array<string, mixed> $m
     * @param 'seller'|'buyer' $role
     */
    private function writeAddressRf(WriterBuffer $w, array $m, string $role): void
    {
        $prefix = $role . '_addr_';
        $hasAny = false;
        foreach (['index', 'region_code', 'region', 'district', 'city', 'street', 'house', 'building', 'flat'] as $part) {
            if (!empty($m[$prefix . $part])) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny && empty($m[$role . '_address'])) {
            return;
        }

        $regionCode = \Ooofix\XmlupdCloud\Core\Address\RegionCodeResolver::resolve(
            (string)($m[$prefix . 'region_code'] ?? ''),
            (string)($m[$prefix . 'region'] ?? ''),
            (string)($m[$prefix . 'city'] ?? ''),
            (string)($m[$prefix . 'index'] ?? ''),
            (string)($m[$role . '_address'] ?? '')
        );

        if ($regionCode === '') {
            return;
        }

        $w->startElement('Адрес');
        $w->startElement('АдрРФ');
        $w->writeAttribute('КодРегион', $regionCode);
        if (!empty($m[$prefix . 'index'])) {
            $w->writeAttribute('Индекс', AddressComponentParser::truncate((string)$m[$prefix . 'index'], 6));
        }
        if (!empty($m[$prefix . 'region'])) {
            $w->writeAttribute('НаимРегион', AddressComponentParser::truncate((string)$m[$prefix . 'region'], 51));
        }
        if (!empty($m[$prefix . 'district'])) {
            $w->writeAttribute('Район', AddressComponentParser::truncate((string)$m[$prefix . 'district'], 255));
        }
        if (!empty($m[$prefix . 'city'])) {
            $w->writeAttribute('Город', AddressComponentParser::truncate((string)$m[$prefix . 'city'], 255));
        }
        if (!empty($m[$prefix . 'street'])) {
            $w->writeAttribute('Улица', AddressComponentParser::truncate((string)$m[$prefix . 'street'], 255));
        }
        if (!empty($m[$prefix . 'house'])) {
            $w->writeAttribute('Дом', AddressComponentParser::truncate((string)$m[$prefix . 'house'], 50));
        }
        if (!empty($m[$prefix . 'building'])) {
            $w->writeAttribute('Корпус', AddressComponentParser::truncate((string)$m[$prefix . 'building'], 50));
        }
        if (!empty($m[$prefix . 'flat'])) {
            $w->writeAttribute('Кварт', AddressComponentParser::truncate((string)$m[$prefix . 'flat'], 50));
        }
        $w->endElement();
        $w->endElement();
    }

    /**
     * @param array<string, mixed> $m
     * @param 'seller'|'buyer' $role
     */
    private function writeFio(WriterBuffer $w, array $m, string $role): void
    {
        $w->startElement('ФИО');
        $w->writeAttribute('Фамилия', (string)($m[$role . '_last_name'] ?? ''));
        $w->writeAttribute('Имя', (string)($m[$role . '_first_name'] ?? ''));
        $middle = (string)($m[$role . '_middle_name'] ?? '');
        if ($middle !== '') {
            $w->writeAttribute('Отчество', $middle);
        }
        $w->endElement();
    }

    /** @param array<string, mixed> $m */
    private function writeProductsTable(WriterBuffer $w, array $m): void
    {
        $products = $m['products'] ?? [];
        if ($products === []) {
            return;
        }

        $totals = $m['totals'] ?? [];

        $w->startElement('ТаблСчФакт');

        foreach ($products as $row) {
            $w->startElement('СведТов');
            $w->writeAttribute('НомСтр', (string)($row['LINE'] ?? ''));
            $w->writeAttribute('НаимТов', (string)($row['NAME'] ?? ''));
            $w->writeAttribute('ОКЕИ_Тов', OkeiMeasureValidator::formatForXml((string)($row['MEASURE_CODE'] ?? '796')));
            $w->writeAttribute('КолТов', $this->formatQty((float)($row['QUANTITY'] ?? 0)));
            $w->writeAttribute('ЦенаТов', $this->formatMoney((float)($row['PRICE'] ?? 0)));
            $w->writeAttribute('СтТовБезНДС', $this->formatMoney((float)($row['SUM_NET'] ?? 0)));
            $w->writeAttribute('НалСт', $this->taxLabel((float)($row['TAX_RATE'] ?? 0)));
            $w->writeAttribute('СтТовУчНал', $this->formatMoney((float)($row['SUM_GROSS'] ?? 0)));
            $w->writeAttribute('НаимЕдИзм', (string)($row['MEASURE'] ?? 'шт'));

            $w->startElement('Акциз');
            $w->startElement('БезАкциз');
            $w->text('без акциза');
            $w->endElement();
            $w->endElement();

            // ФНС 5.03 / Diadoc: контейнер СумНал -> вложенный СумНал со значением (см. edo_6608_*.xml)
            $w->startElement('СумНал');
            $w->startElement('СумНал');
            $w->text($this->formatMoney((float)($row['TAX_SUM'] ?? 0)));
            $w->endElement();
            $w->endElement();

            $w->endElement();
        }

        $w->startElement('ВсегоОпл');
        $w->writeAttribute('СтТовБезНДСВсего', $this->formatMoney((float)($totals['SUM_NET'] ?? 0)));
        $w->writeAttribute('СтТовУчНалВсего', $this->formatMoney((float)($totals['SUM_GROSS'] ?? 0)));
        $w->startElement('СумНалВсего');
        $w->startElement('СумНал');
        $w->text($this->formatMoney((float)($totals['TAX_SUM'] ?? 0)));
        $w->endElement();
        $w->endElement();
        $w->endElement();

        $w->endElement();
    }

    /** @param array<string, mixed> $m */
    private function writeSvProdPer(WriterBuffer $w, array $m): void
    {
        $products = $m['products'] ?? [];
        $firstProduct = $products[0]['NAME'] ?? ($m['products_text'] ?? '');

        $w->startElement('СвПродПер');
        $w->startElement('СвПер');
        $w->writeAttribute('СодОпер', (string)$firstProduct);
        $w->writeAttribute('ДатаПер', (string)$m['doc_date']);

        $this->writeTransferBasis($w, $m);

        $w->endElement();
        $w->endElement();
    }

    /** @param array<string, mixed> $m */
    private function writeTransferBasis(WriterBuffer $w, array $m): void
    {
        $name = trim((string)($m['transfer_basis_name'] ?? ''));
        $number = trim((string)($m['transfer_basis_number'] ?? ''));
        $date = trim((string)($m['transfer_basis_date'] ?? ''));

        if ($name !== '' && $number !== '' && $date !== '') {
            $w->startElement('ОснПер');
            $w->writeAttribute('РеквНаимДок', $name);
            $w->writeAttribute('РеквНомерДок', $number);
            $w->writeAttribute('РеквДатаДок', $date);
            $w->endElement();

            return;
        }

        // XSD не допускает пустые атрибуты ОснПер — только признак «без документа-основания»
        $w->startElement('БезДокОснПер');
        $w->text('1');
        $w->endElement();
    }

    /** @param array<string, mixed> $m */
    private function writeSignatory(WriterBuffer $w, array $m): void
    {
        $w->startElement('Подписант');
        $w->writeAttribute('СпосПодтПолном', '6');
        $w->writeAttribute('Должн', (string)($m['signatory_position'] ?? 'Сотрудник'));

        $w->startElement('ФИО');
        $w->writeAttribute('Фамилия', (string)($m['signatory_last_name'] ?? ''));
        $w->writeAttribute('Имя', (string)($m['signatory_first_name'] ?? ''));
        $middle = (string)($m['signatory_middle_name'] ?? '');
        if ($middle !== '') {
            $w->writeAttribute('Отчество', $middle);
        }
        $w->endElement();

        $w->endElement();
    }

    private function formatMoney(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    private function formatQty(float $n): string
    {
        $s = number_format($n, 3, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    }

    private function taxLabel(float $rate): string
    {
        if ($rate <= 0) {
            return 'без НДС';
        }

        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
    }
}
