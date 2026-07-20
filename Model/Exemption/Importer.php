<?php
namespace Sandy\WalmartSync\Model\Exemption;

use Sandy\WalmartSync\Model\SkuStorage;

class Importer
{
    private $storage;
    private $statusUpdater;

    public function __construct(SkuStorage $storage, StatusUpdater $statusUpdater)
    {
        $this->storage = $storage;
        $this->statusUpdater = $statusUpdater;
    }

    public function execute($file, $defaultStatus = null, $execute = false)
    {
        $path = trim((string)$file);
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('A readable CSV file is required.');
        }
        $defaultStatus = $defaultStatus === null || $defaultStatus === '' ? null : $this->statusUpdater->normalize($defaultStatus);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('Unable to open exemption CSV.');
        }
        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            throw new \RuntimeException('The exemption CSV does not contain a header row.');
        }
        $columns = $this->columns($header);
        if ($columns['sku'] === null) {
            fclose($handle);
            throw new \RuntimeException('The exemption CSV must contain a SKU or Walmart SKU column.');
        }
        if ($columns['status'] === null && $defaultStatus === null) {
            fclose($handle);
            throw new \RuntimeException('The CSV must contain a status column or --default-status must be supplied.');
        }

        $updates = [];
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $sku = isset($row[$columns['sku']]) ? trim((string)$row[$columns['sku']]) : '';
            if ($sku === '') {
                continue;
            }
            try {
                $statusValue = $columns['status'] === null || !isset($row[$columns['status']]) || trim((string)$row[$columns['status']]) === ''
                    ? $defaultStatus
                    : $this->statusUpdater->normalize($row[$columns['status']]);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = sprintf('Line %d, SKU %s: %s', $line, $sku, $exception->getMessage());
                continue;
            }
            if ($statusValue === null) {
                $errors[] = sprintf('Line %d, SKU %s: no exemption status was supplied.', $line, $sku);
                continue;
            }
            $record = $this->storage->getByWalmartSku($sku);
            if (!$record) {
                $errors[] = sprintf('Line %d, SKU %s: Walmart SKU is not present in the local catalog.', $line, $sku);
                continue;
            }
            if (isset($updates[$sku])) {
                $errors[] = sprintf('Line %d, SKU %s: duplicate SKU in import file.', $line, $sku);
                continue;
            }
            $updates[$sku] = ['status' => $statusValue, 'record' => $record];
        }
        fclose($handle);

        if ($errors) {
            return ['processed' => count($updates), 'updated' => 0, 'errors' => $errors, 'executed' => false];
        }

        $updated = 0;
        if ($execute) {
            foreach ($updates as $sku => $update) {
                $record = $update['record'];
                $status = $update['status'];
                $this->statusUpdater->update($sku, $status, $record);
                $updated++;
            }
        }

        return ['processed' => count($updates), 'updated' => $updated, 'errors' => [], 'executed' => (bool)$execute];
    }

    private function columns(array $header)
    {
        $result = ['sku' => null, 'status' => null];
        foreach ($header as $index => $value) {
            $name = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$value)));
            if (in_array($name, ['sku', 'walmartsku', 'partneritemid'], true)) {
                $result['sku'] = $index;
            }
            if (in_array($name, ['status', 'exemptionstatus', 'walmartreturnexemptionstatus', 'decision', 'result'], true)) {
                $result['status'] = $index;
            }
        }
        return $result;
    }

}
