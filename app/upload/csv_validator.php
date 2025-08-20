<?php
//+------------------------------------------------------------------+
//| csv_validator.php                                               |
//| Validador y Analizador Avanzado de CSV                          |
//| Copyright 2025, Elysium Media FZCO                              |
//| Sistema de Validación Profesional para Trading                  |
//+------------------------------------------------------------------+

class CSVValidator {
    
    private $errors = [];
    private $warnings = [];
    private $info = [];
    private $stats = [];
    private $config = [];
    
    /**
     * Constructor con configuración por defecto
     */
    public function __construct($config = []) {
        $this->config = array_merge([
            'max_file_size' => 50 * 1024 * 1024, // 50MB
            'allowed_extensions' => ['csv', 'txt'],
            'required_columns' => [
                'ticket', 'time', 'type', 'symbol', 'volume', 
                'price', 'profit'
            ],
            'optional_columns' => [
                'sl', 'tp', 'commission', 'swap', 'comment', 
                'magic', 'entry', 'reason', 'position_id', 'order_id'
            ],
            'date_formats' => [
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y/m/d H:i:s',
                'd/m/Y H:i:s',
                'm/d/Y H:i:s',
                'd.m.Y H:i:s',
                'Y-m-d\TH:i:s'
            ],
            'decimal_separator' => '.',
            'thousand_separator' => '',
            'encoding' => 'UTF-8',
            'delimiter_detection' => true,
            'skip_empty_lines' => true,
            'trim_values' => true
        ], $config);
    }
    
    /**
     * Validar archivo CSV completo
     */
    public function validateFile($filepath) {
        $this->resetValidation();
        
        // Validaciones básicas del archivo
        if (!$this->validateFileExists($filepath)) return false;
        if (!$this->validateFileSize($filepath)) return false;
        if (!$this->validateFileExtension($filepath)) return false;
        if (!$this->validateFileReadable($filepath)) return false;
        
        // Detectar configuración del CSV
        $csvConfig = $this->detectCSVFormat($filepath);
        if (!$csvConfig) return false;
        
        // Procesar y validar contenido
        $data = $this->processCSVContent($filepath, $csvConfig);
        if (!$data) return false;
        
        // Validaciones de datos
        $this->validateDataIntegrity($data);
        $this->validateDataConsistency($data);
        $this->calculateStatistics($data);
        
        return $this->isValid();
    }
    
    /**
     * Detectar formato del CSV automáticamente
     */
    private function detectCSVFormat($filepath) {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            $this->addError('No se puede abrir el archivo para lectura');
            return false;
        }
        
        // Leer primeras líneas para análisis
        $sampleLines = [];
        for ($i = 0; $i < 5 && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line !== false) {
                $sampleLines[] = $line;
            }
        }
        fclose($handle);
        
        if (empty($sampleLines)) {
            $this->addError('El archivo está vacío');
            return false;
        }
        
        // Detectar encoding
        $encoding = $this->detectEncoding($sampleLines[0]);
        
        // Detectar delimitador
        $delimiter = $this->detectDelimiter($sampleLines);
        
        // Detectar si tiene BOM
        $hasBOM = $this->hasBOM($sampleLines[0]);
        
        return [
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'has_bom' => $hasBOM,
            'has_header' => $this->hasHeader($sampleLines[0], $delimiter)
        ];
    }
    
    /**
     * Detectar encoding del archivo
     */
    private function detectEncoding($sample) {
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'];
        
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($sample, $encoding)) {
                $this->addInfo("Encoding detectado: $encoding");
                return $encoding;
            }
        }
        
        return 'UTF-8'; // Default
    }
    
    /**
     * Detectar delimitador del CSV
     */
    private function detectDelimiter($lines) {
        $delimiters = [',', ';', "\t", '|'];
        $scores = [];
        
        foreach ($delimiters as $delimiter) {
            $scores[$delimiter] = 0;
            foreach ($lines as $line) {
                $count = substr_count($line, $delimiter);
                if ($count > 0) {
                    $scores[$delimiter] += $count;
                }
            }
        }
        
        arsort($scores);
        $bestDelimiter = key($scores);
        
        $this->addInfo("Delimitador detectado: " . ($bestDelimiter === "\t" ? 'TAB' : "'$bestDelimiter'"));
        
        return $bestDelimiter;
    }
    
    /**
     * Verificar si el archivo tiene BOM
     */
    private function hasBOM($firstLine) {
        $bom = pack('H*','EFBBBF');
        return substr($firstLine, 0, 3) === $bom;
    }
    
    /**
     * Verificar si la primera línea es un header
     */
    private function hasHeader($firstLine, $delimiter) {
        $fields = str_getcsv($firstLine, $delimiter);
        
        // Si contiene palabras clave comunes de headers
        $headerKeywords = ['ticket', 'time', 'date', 'type', 'symbol', 'volume', 'price'];
        foreach ($fields as $field) {
            if (in_array(strtolower(trim($field)), $headerKeywords)) {
                return true;
            }
        }
        
        // Si todos los campos NO son numéricos, probablemente es header
        $nonNumeric = 0;
        foreach ($fields as $field) {
            if (!is_numeric(trim($field))) {
                $nonNumeric++;
            }
        }
        
        return $nonNumeric > count($fields) / 2;
    }
    
    /**
     * Procesar contenido del CSV
     */
    private function processCSVContent($filepath, $config) {
        $data = [];
        $lineNumber = 0;
        
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return false;
        }
        
        // Saltar BOM si existe
        if ($config['has_bom']) {
            fseek($handle, 3);
        }
        
        // Leer headers
        $headers = [];
        if ($config['has_header']) {
            $headerLine = fgetcsv($handle, 0, $config['delimiter']);
            if ($headerLine) {
                $headers = array_map(function($h) {
                    return strtolower(trim($h));
                }, $headerLine);
                
                // Validar columnas requeridas
                $this->validateRequiredColumns($headers);
            }
            $lineNumber++;
        }
        
        // Procesar datos
        while (($row = fgetcsv($handle, 0, $config['delimiter'])) !== FALSE) {
            $lineNumber++;
            
            // Saltar líneas vacías
            if ($this->config['skip_empty_lines'] && empty(array_filter($row))) {
                continue;
            }
            
            // Validar número de columnas
            if (count($row) !== count($headers)) {
                $this->addWarning("Línea $lineNumber: Número de columnas no coincide");
                continue;
            }
            
            // Crear array asociativo
            $record = [];
            foreach ($headers as $index => $header) {
                $value = isset($row[$index]) ? $row[$index] : '';
                if ($this->config['trim_values']) {
                    $value = trim($value);
                }
                $record[$header] = $value;
            }
            
            // Validar y limpiar registro
            $cleanRecord = $this->validateAndCleanRecord($record, $lineNumber);
            if ($cleanRecord) {
                $data[] = $cleanRecord;
            }
        }
        
        fclose($handle);
        
        $this->addInfo("Total de registros procesados: " . count($data));
        
        return $data;
    }
    
    /**
     * Validar y limpiar un registro
     */
    private function validateAndCleanRecord($record, $lineNumber) {
        $clean = [];
        
        // Ticket
        if (empty($record['ticket'])) {
            $this->addError("Línea $lineNumber: Ticket vacío");
            return false;
        }
        $clean['ticket'] = intval($record['ticket']);
        if ($clean['ticket'] <= 0) {
            $this->addError("Línea $lineNumber: Ticket inválido");
            return false;
        }
        
        // Time
        $clean['time'] = $this->validateDateTime($record['time'], $lineNumber);
        if (!$clean['time']) {
            return false;
        }
        
        // Type
        $clean['type'] = intval($record['type'] ?? 0);
        if ($clean['type'] < 0 || $clean['type'] > 17) {
            $this->addWarning("Línea $lineNumber: Tipo de operación fuera de rango");
        }
        
        // Symbol
        $clean['symbol'] = $this->normalizeSymbol($record['symbol'] ?? '');
        
        // Numeric fields
        $numericFields = [
            'volume' => 0,
            'price' => 0,
            'sl' => 0,
            'tp' => 0,
            'commission' => 0,
            'swap' => 0,
            'profit' => 0
        ];
        
        foreach ($numericFields as $field => $default) {
            $value = $record[$field] ?? $default;
            // Convertir separadores si es necesario
            $value = str_replace(',', '.', $value);
            $value = str_replace(' ', '', $value);
            $clean[$field] = floatval($value);
        }
        
        // Integer fields
        $intFields = [
            'magic' => 0,
            'entry' => 0,
            'reason' => 0,
            'position_id' => 0,
            'order_id' => 0
        ];
        
        foreach ($intFields as $field => $default) {
            $clean[$field] = intval($record[$field] ?? $default);
        }
        
        // Comment
        $clean['comment'] = substr($record['comment'] ?? '', 0, 255);
        
        return $clean;
    }
    
    /**
     * Validar fecha/hora
     */
    private function validateDateTime($datetime, $lineNumber) {
        if (empty($datetime)) {
            $this->addError("Línea $lineNumber: Fecha/hora vacía");
            return false;
        }
        
        // Intentar con formatos configurados
        foreach ($this->config['date_formats'] as $format) {
            $date = DateTime::createFromFormat($format, $datetime);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        // Intentar con strtotime
        $timestamp = strtotime($datetime);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        $this->addError("Línea $lineNumber: Formato de fecha inválido: $datetime");
        return false;
    }
    
    /**
     * Normalizar símbolo
     */
    private function normalizeSymbol($symbol) {
        $symbol = strtoupper(trim($symbol));
        
        // Remover sufijos comunes
        $symbol = preg_replace('/\.(ecn|pro|std|raw|zero)$/i', '', $symbol);
        
        // Remover caracteres especiales
        $symbol = preg_replace('/[^A-Z0-9]/', '', $symbol);
        
        // Mapeo de símbolos comunes
        $symbolMap = [
            'GOLD' => 'XAUUSD',
            'SILVER' => 'XAGUSD',
            'OIL' => 'XTIUSD',
            'WTI' => 'XTIUSD',
            'BRENT' => 'XBRUSD'
        ];
        
        if (isset($symbolMap[$symbol])) {
            return $symbolMap[$symbol];
        }
        
        return $symbol;
    }
    
    /**
     * Validar integridad de datos
     */
    private function validateDataIntegrity($data) {
        $tickets = [];
        $positions = [];
        
        foreach ($data as $index => $record) {
            // Verificar tickets duplicados
            if (in_array($record['ticket'], $tickets)) {
                $this->addWarning("Ticket duplicado: #{$record['ticket']}");
            }
            $tickets[] = $record['ticket'];
            
            // Verificar posiciones
            if ($record['position_id'] > 0) {
                if (!isset($positions[$record['position_id']])) {
                    $positions[$record['position_id']] = [
                        'opens' => 0,
                        'closes' => 0,
                        'symbol' => $record['symbol']
                    ];
                }
                
                if ($record['entry'] == 0) {
                    $positions[$record['position_id']]['opens']++;
                } else {
                    $positions[$record['position_id']]['closes']++;
                }
            }
        }
        
        // Verificar posiciones balanceadas
        foreach ($positions as $posId => $pos) {
            if ($pos['opens'] != $pos['closes']) {
                $this->addWarning("Posición #$posId no balanceada: {$pos['opens']} aperturas, {$pos['closes']} cierres");
            }
        }
    }
    
    /**
     * Validar consistencia de datos
     */
    private function validateDataConsistency($data) {
        $balance = 0;
        $previousTime = null;
        
        foreach ($data as $record) {
            // Verificar orden cronológico
            if ($previousTime && strtotime($record['time']) < strtotime($previousTime)) {
                $this->addWarning("Orden cronológico incorrecto en ticket #{$record['ticket']}");
            }
            $previousTime = $record['time'];
            
            // Calcular balance running
            if ($record['type'] == 2) { // Balance operation
                $balance += $record['profit'];
            } elseif ($record['type'] == 0 || $record['type'] == 1) { // Trading
                $balance += $record['profit'] + $record['commission'] + $record['swap'];
            }
            
            // Validaciones lógicas
            if ($record['type'] == 0 || $record['type'] == 1) { // Trading
                if ($record['volume'] <= 0) {
                    $this->addWarning("Volumen inválido en ticket #{$record['ticket']}");
                }
                if ($record['price'] <= 0) {
                    $this->addWarning("Precio inválido en ticket #{$record['ticket']}");
                }
            }
        }
        
        $this->stats['final_balance'] = $balance;
    }
    
    /**
     * Calcular estadísticas
     */
    private function calculateStatistics($data) {
        $this->stats['total_records'] = count($data);
        $this->stats['total_trades'] = 0;
        $this->stats['total_deposits'] = 0;
        $this->stats['total_withdrawals'] = 0;
        $this->stats['gross_profit'] = 0;
        $this->stats['gross_loss'] = 0;
        $this->stats['total_commission'] = 0;
        $this->stats['total_swap'] = 0;
        $this->stats['symbols'] = [];
        
        foreach ($data as $record) {
            if ($record['type'] == 0 || $record['type'] == 1) {
                $this->stats['total_trades']++;
                
                if ($record['profit'] > 0) {
                    $this->stats['gross_profit'] += $record['profit'];
                } else {
                    $this->stats['gross_loss'] += abs($record['profit']);
                }
                
                $this->stats['total_commission'] += $record['commission'];
                $this->stats['total_swap'] += $record['swap'];
                
                if (!empty($record['symbol'])) {
                    if (!in_array($record['symbol'], $this->stats['symbols'])) {
                        $this->stats['symbols'][] = $record['symbol'];
                    }
                }
            } elseif ($record['type'] == 2) {
                if ($record['profit'] > 0) {
                    $this->stats['total_deposits']++;
                } else {
                    $this->stats['total_withdrawals']++;
                }
            }
        }
        
        // Calcular métricas
        if ($this->stats['gross_loss'] > 0) {
            $this->stats['profit_factor'] = round($this->stats['gross_profit'] / $this->stats['gross_loss'], 2);
        } else {
            $this->stats['profit_factor'] = $this->stats['gross_profit'] > 0 ? 999.99 : 0;
        }
        
        $this->stats['net_profit'] = $this->stats['gross_profit'] - $this->stats['gross_loss'];
    }
    
    /**
     * Validaciones básicas del archivo
     */
    private function validateFileExists($filepath) {
        if (!file_exists($filepath)) {
            $this->addError('El archivo no existe');
            return false;
        }
        return true;
    }
    
    private function validateFileSize($filepath) {
        $size = filesize($filepath);
        if ($size > $this->config['max_file_size']) {
            $this->addError('El archivo excede el tamaño máximo permitido');
            return false;
        }
        if ($size == 0) {
            $this->addError('El archivo está vacío');
            return false;
        }
        return true;
    }
    
    private function validateFileExtension($filepath) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config['allowed_extensions'])) {
            $this->addError('Extensión de archivo no permitida');
            return false;
        }
        return true;
    }
    
    private function validateFileReadable($filepath) {
        if (!is_readable($filepath)) {
            $this->addError('El archivo no tiene permisos de lectura');
            return false;
        }
        return true;
    }
    
    private function validateRequiredColumns($headers) {
        $missing = array_diff($this->config['required_columns'], $headers);
        if (!empty($missing)) {
            $this->addError('Columnas requeridas faltantes: ' . implode(', ', $missing));
            return false;
        }
        return true;
    }
    
    /**
     * Métodos de utilidad
     */
    private function addError($message) {
        $this->errors[] = $message;
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
    }
    
    private function addInfo($message) {
        $this->info[] = $message;
    }
    
    private function resetValidation() {
        $this->errors = [];
        $this->warnings = [];
        $this->info = [];
        $this->stats = [];
    }
    
    public function isValid() {
        return empty($this->errors);
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public function getInfo() {
        return $this->info;
    }
    
    public function getStats() {
        return $this->stats;
    }
    
    public function getValidationReport() {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'info' => $this->info,
            'statistics' => $this->stats
        ];
    }
}

// Uso del validador
if (isset($_FILES['csvFile'])) {
    $validator = new CSVValidator();
    $filepath = $_FILES['csvFile']['tmp_name'];
    
    if ($validator->validateFile($filepath)) {
        $report = $validator->getValidationReport();
        echo json_encode([
            'success' => true,
            'report' => $report
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $validator->getErrors(),
            'warnings' => $validator->getWarnings()
        ]);
    }
}
?>