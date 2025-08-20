<?php
//+------------------------------------------------------------------+
//| index.php - Sistema de Upload CSV con Protección                |
//| Copyright 2025, Elysium Media FZCO                              |
//+------------------------------------------------------------------+

// PROTECCIÓN SIMPLE CON SESIONES
session_start();

// Si no está autenticado, redirigir a login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Obtener datos del usuario actual
$currentUser = [
    'username' => $_SESSION['username'] ?? 'Usuario',
    'name' => ucfirst($_SESSION['username'] ?? 'Usuario'),
    'role' => 'uploader'
];

// Procesar logout si se solicita
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador de Datos Históricos CSV - Elysium Trading v7.0</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --bg-primary: #0f0f1a;
            --bg-secondary: #1a1a2e;
            --bg-card: #16162b;
            --bg-hover: #1e1e3a;
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
            --text-muted: #718096;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
            border-radius: 1rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 1rem;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .upload-zone {
            border: 2px dashed rgba(99, 102, 241, 0.3);
            border-radius: 0.75rem;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s;
            background: rgba(99, 102, 241, 0.02);
            cursor: pointer;
        }
        
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }
        
        .upload-zone.has-file {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .upload-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .upload-text {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .file-info {
            display: none;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            text-align: left;
        }
        
        .file-info.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: var(--bg-primary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .validation-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .checkbox-group:hover {
            background: rgba(99, 102, 241, 0.05);
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            accent-color: var(--primary);
            cursor: pointer;
        }
        
        .process-log {
            background: var(--bg-primary);
            border-radius: 0.5rem;
            padding: 1rem;
            height: 200px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.813rem;
            line-height: 1.5;
            color: var(--text-muted);
            display: none;
        }
        
        .process-log.active {
            display: block;
        }
        
        .log-entry {
            padding: 0.25rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .log-entry.success {
            color: var(--success);
        }
        
        .log-entry.warning {
            color: var(--warning);
        }
        
        .log-entry.error {
            color: var(--danger);
        }
        
        .log-entry.info {
            color: var(--info);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .template-section {
            background: rgba(99, 102, 241, 0.05);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .template-title {
            font-weight: 600;
            color: var(--primary);
        }
        
        .columns-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            font-size: 0.813rem;
            color: var(--text-secondary);
        }
        
        .column-item {
            padding: 0.5rem;
            background: var(--bg-primary);
            border-radius: 0.25rem;
            font-family: 'Courier New', monospace;
        }
        
        .progress-bar {
            display: none;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-bar.active {
            display: block;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 2px;
            width: 0%;
            transition: width 0.3s;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: none;
        }
        
        .alert.active {
            display: block;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: var(--warning);
        }
        
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .columns-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .columns-list {
                grid-template-columns: 1fr;
            }
            
            .validation-options {
                grid-template-columns: 1fr;
            }
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Importador de Datos Históricos CSV</h1>
            <p>Sistema Profesional de Importación de Datos de Trading - Elysium v7.0</p>
        </div>
        
        <!-- Navigation Buttons -->
        <div class="nav-buttons">
            <a href="../index.php" class="btn btn-secondary">
                <span class="material-icons-outlined">arrow_back</span>
                Volver al Dashboard
            </a>
            <button class="btn btn-primary" onclick="downloadTemplate()">
                <span class="material-icons-outlined">download</span>
                Descargar Plantilla CSV
            </button>
            <button class="btn btn-secondary" onclick="showInstructions()">
                <span class="material-icons-outlined">help_outline</span>
                Instrucciones
            </button>
        </div>
        
        <!-- Alert Messages -->
        <div id="alertSuccess" class="alert alert-success">
            <strong>✅ Éxito!</strong> <span id="successMessage"></span>
        </div>
        
        <div id="alertError" class="alert alert-error">
            <strong>❌ Error!</strong> <span id="errorMessage"></span>
        </div>
        
        <div id="alertWarning" class="alert alert-warning">
            <strong>⚠️ Advertencia!</strong> <span id="warningMessage"></span>
        </div>
        
        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Upload Section -->
            <div class="card">
                <h2>
                    <span class="material-icons-outlined">cloud_upload</span>
                    Cargar Archivo CSV
                </h2>
                
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
                    <span class="material-icons-outlined upload-icon">upload_file</span>
                    <div class="upload-text">
                        Arrastra y suelta tu archivo CSV aquí<br>
                        o haz clic para seleccionar
                    </div>
                    <div class="btn btn-primary">Seleccionar Archivo</div>
                    <input type="file" id="csvFile" accept=".csv" style="display: none;">
                </div>
                
                <div class="file-info" id="fileInfo">
                    <strong>Archivo seleccionado:</strong>
                    <div id="fileName"></div>
                    <div id="fileSize"></div>
                    <div id="fileRows"></div>
                </div>
                
                <div class="form-group" style="margin-top: 2rem;">
                    <label for="accountName">Nombre de la Cuenta *</label>
                    <input type="text" id="accountName" class="form-control" placeholder="Ej: Cuenta Principal 2024">
                </div>
                
                <div class="form-group">
                    <label for="accountLogin">Número de Cuenta (Login) *</label>
                    <input type="number" id="accountLogin" class="form-control" placeholder="Ej: 12345678">
                </div>
                
                <div class="form-group">
                    <label for="brokerName">Nombre del Broker *</label>
                    <input type="text" id="brokerName" class="form-control" placeholder="Ej: MetaQuotes Software Corp.">
                </div>
                
                <div class="form-group">
                    <label for="serverName">Servidor</label>
                    <input type="text" id="serverName" class="form-control" placeholder="Ej: MetaQuotes-Demo">
                </div>
                
                <div class="form-group">
                    <label for="accountCurrency">Moneda de la Cuenta</label>
                    <select id="accountCurrency" class="form-control">
                        <option value="USD">USD - Dólar Estadounidense</option>
                        <option value="EUR">EUR - Euro</option>
                        <option value="GBP">GBP - Libra Esterlina</option>
                        <option value="JPY">JPY - Yen Japonés</option>
                        <option value="CHF">CHF - Franco Suizo</option>
                        <option value="CAD">CAD - Dólar Canadiense</option>
                        <option value="AUD">AUD - Dólar Australiano</option>
                        <option value="NZD">NZD - Dólar Neozelandés</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="accountLeverage">Apalancamiento</label>
                    <select id="accountLeverage" class="form-control">
                        <option value="1">1:1</option>
                        <option value="10">1:10</option>
                        <option value="20">1:20</option>
                        <option value="30">1:30</option>
                        <option value="50">1:50</option>
                        <option value="100" selected>1:100</option>
                        <option value="200">1:200</option>
                        <option value="300">1:300</option>
                        <option value="400">1:400</option>
                        <option value="500">1:500</option>
                        <option value="1000">1:1000</option>
                    </select>
                </div>
            </div>
            
            <!-- Configuration Section -->
            <div class="card">
                <h2>
                    <span class="material-icons-outlined">settings</span>
                    Configuración de Importación
                </h2>
                
                <div class="template-section">
                    <div class="template-header">
                        <span class="template-title">📋 Columnas Requeridas en el CSV</span>
                        <button class="btn btn-secondary" onclick="copyColumnNames()">
                            <span class="material-icons-outlined">content_copy</span>
                            Copiar
                        </button>
                    </div>
                    <div class="columns-list">
                        <div class="column-item">ticket</div>
                        <div class="column-item">time</div>
                        <div class="column-item">type</div>
                        <div class="column-item">symbol</div>
                        <div class="column-item">volume</div>
                        <div class="column-item">price</div>
                        <div class="column-item">sl</div>
                        <div class="column-item">tp</div>
                        <div class="column-item">commission</div>
                        <div class="column-item">swap</div>
                        <div class="column-item">profit</div>
                        <div class="column-item">comment</div>
                        <div class="column-item">magic</div>
                        <div class="column-item">entry</div>
                        <div class="column-item">reason</div>
                    </div>
                </div>
                
                <h3 style="margin-bottom: 1rem;">Opciones de Validación</h3>
                <div class="validation-options">
                    <label class="checkbox-group">
                        <input type="checkbox" id="validateDuplicates" checked>
                        <span>Detectar tickets duplicados</span>
                    </label>
                    
                    <label class="checkbox-group">
                        <input type="checkbox" id="validateDates" checked>
                        <span>Validar formato de fechas</span>
                    </label>
                    
                    <label class="checkbox-group">
                        <input type="checkbox" id="validateNumbers" checked>
                        <span>Validar campos numéricos</span>
                    </label>
                    
                    <label class="checkbox-group">
                        <input type="checkbox" id="calculateBalance" checked>
                        <span>Calcular balance automático</span>
                    </label>
                </div>
                
                <h3 style="margin-bottom: 1rem;">Estadísticas del Archivo</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="totalRows">0</div>
                        <div class="stat-label">Total Filas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalTrades">0</div>
                        <div class="stat-label">Trades</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalDeposits">0</div>
                        <div class="stat-label">Depósitos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalWithdrawals">0</div>
                        <div class="stat-label">Retiros</div>
                    </div>
                </div>
                
                <button class="btn btn-primary" style="width: 100%; margin-top: 1rem;" onclick="startImport()" id="importBtn" disabled>
                    <span class="material-icons-outlined">play_arrow</span>
                    Iniciar Importación
                </button>
                
                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                
                <h3 style="margin-bottom: 1rem; margin-top: 2rem;">Registro de Proceso</h3>
                <div class="process-log" id="processLog"></div>
            </div>
        </div>
        
        <!-- History Section -->
        <div class="card">
            <h2>
                <span class="material-icons-outlined">history</span>
                Historial de Importaciones
            </h2>
            
            <div id="importHistory">
                <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
                    No hay importaciones recientes
                </p>
            </div>
        </div>
    </div>
    
    <!-- Instructions Modal -->
    <div class="modal" id="instructionsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📚 Instrucciones de Uso</h2>
                <button class="modal-close" onclick="closeModal('instructionsModal')">&times;</button>
            </div>
            
            <h3>Preparación del Archivo CSV</h3>
            <ol style="color: var(--text-secondary); line-height: 1.8;">
                <li>Descarga la plantilla CSV haciendo clic en "Descargar Plantilla CSV"</li>
                <li>Abre el archivo en Excel o Google Sheets</li>
                <li>Completa los datos según el formato especificado</li>
                <li>Guarda el archivo en formato CSV (UTF-8)</li>
            </ol>
            
            <h3>Formato de Datos</h3>
            <ul style="color: var(--text-secondary); line-height: 1.8;">
                <li><strong>Fechas:</strong> Formato YYYY-MM-DD HH:MM:SS</li>
                <li><strong>Tipos de operación:</strong> 0=Buy, 1=Sell, 2=Balance, 3=Credit</li>
                <li><strong>Entry:</strong> 0=In, 1=Out</li>
                <li><strong>Decimales:</strong> Usar punto (.) como separador decimal</li>
            </ul>
            
            <h3>Proceso de Importación</h3>
            <ol style="color: var(--text-secondary); line-height: 1.8;">
                <li>Selecciona o arrastra tu archivo CSV</li>
                <li>Completa la información de la cuenta</li>
                <li>Revisa las opciones de validación</li>
                <li>Haz clic en "Iniciar Importación"</li>
                <li>Espera a que termine el proceso</li>
            </ol>
            
            <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 0.5rem; padding: 1rem; margin-top: 1rem;">
                <strong style="color: var(--warning);">⚠️ Importante:</strong>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                    Los datos importados se tratarán como históricos y no se podrán modificar desde el EA.
                    Asegúrate de que la información sea correcta antes de importar.
                </p>
            </div>
        </div>
    </div>
    
    <script>
        let selectedFile = null;
        let csvData = [];
        let isProcessing = false;
        
        // Drag and Drop
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('csvFile');
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === 'text/csv') {
                handleFileSelect(files[0]);
            } else {
                showAlert('error', 'Por favor selecciona un archivo CSV válido');
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                showAlert('error', 'Por favor selecciona un archivo CSV válido');
                return;
            }
            
            selectedFile = file;
            uploadZone.classList.add('has-file');
            
            // Show file info
            document.getElementById('fileInfo').classList.add('active');
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = `Tamaño: ${formatFileSize(file.size)}`;
            
            // Read and preview file
            const reader = new FileReader();
            reader.onload = function(e) {
                parseCSV(e.target.result);
            };
            reader.readAsText(file);
            
            // Enable import button
            document.getElementById('importBtn').disabled = false;
        }
        
        function parseCSV(content) {
            const lines = content.split('\n').filter(line => line.trim());
            const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
            
            csvData = [];
            let tradeCount = 0;
            let depositCount = 0;
            let withdrawalCount = 0;
            
            for (let i = 1; i < lines.length; i++) {
                const values = parseCSVLine(lines[i]);
                if (values.length === headers.length) {
                    const row = {};
                    headers.forEach((header, index) => {
                        row[header] = values[index];
                    });
                    csvData.push(row);
                    
                    // Count types
                    const type = parseInt(row.type);
                    if (type === 0 || type === 1) {
                        tradeCount++;
                    } else if (type === 2 && parseFloat(row.profit) > 0) {
                        depositCount++;
                    } else if (type === 2 && parseFloat(row.profit) < 0) {
                        withdrawalCount++;
                    }
                }
            }
            
            // Update stats
            document.getElementById('totalRows').textContent = csvData.length;
            document.getElementById('totalTrades').textContent = tradeCount;
            document.getElementById('totalDeposits').textContent = depositCount;
            document.getElementById('totalWithdrawals').textContent = withdrawalCount;
            document.getElementById('fileRows').textContent = `Filas de datos: ${csvData.length}`;
            
            addLog('info', `Archivo cargado: ${csvData.length} registros encontrados`);
        }
        
        function parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            
            result.push(current.trim());
            return result;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        async function startImport() {
            if (isProcessing) return;
            
            // Validate required fields
            const accountName = document.getElementById('accountName').value.trim();
            const accountLogin = document.getElementById('accountLogin').value.trim();
            const brokerName = document.getElementById('brokerName').value.trim();
            
            if (!accountName || !accountLogin || !brokerName) {
                showAlert('error', 'Por favor completa todos los campos requeridos');
                return;
            }
            
            if (!selectedFile || csvData.length === 0) {
                showAlert('error', 'Por favor selecciona un archivo CSV válido');
                return;
            }
            
            isProcessing = true;
            document.getElementById('importBtn').disabled = true;
            document.getElementById('progressBar').classList.add('active');
            document.getElementById('processLog').classList.add('active');
            clearLog();
            
            addLog('info', '🚀 Iniciando proceso de importación...');
            
            // Prepare form data
            const formData = new FormData();
            formData.append('csvFile', selectedFile);
            formData.append('accountName', accountName);
            formData.append('accountLogin', accountLogin);
            formData.append('brokerName', brokerName);
            formData.append('serverName', document.getElementById('serverName').value);
            formData.append('accountCurrency', document.getElementById('accountCurrency').value);
            formData.append('accountLeverage', document.getElementById('accountLeverage').value);
            formData.append('validateDuplicates', document.getElementById('validateDuplicates').checked);
            formData.append('validateDates', document.getElementById('validateDates').checked);
            formData.append('validateNumbers', document.getElementById('validateNumbers').checked);
            formData.append('calculateBalance', document.getElementById('calculateBalance').checked);
            
            try {
                const response = await fetch('api/process_csv_import.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    addLog('success', '✅ ' + result.message);
                    
                    // Update progress
                    updateProgress(100);
                    
                    // Show import details
                    if (result.details) {
                        addLog('info', `📊 Registros procesados: ${result.details.processed}`);
                        addLog('info', `✅ Registros importados: ${result.details.imported}`);
                        if (result.details.skipped > 0) {
                            addLog('warning', `⚠️ Registros omitidos: ${result.details.skipped}`);
                        }
                        if (result.details.errors > 0) {
                            addLog('error', `❌ Errores encontrados: ${result.details.errors}`);
                        }
                    }
                    
                    // Update history
                    loadImportHistory();
                    
                    // Reset form after 3 seconds
                    setTimeout(() => {
                        resetForm();
                    }, 3000);
                    
                } else {
                    showAlert('error', result.message || 'Error al procesar el archivo');
                    addLog('error', '❌ ' + (result.message || 'Error desconocido'));
                    
                    if (result.errors && Array.isArray(result.errors)) {
                        result.errors.forEach(error => {
                            addLog('error', `  - ${error}`);
                        });
                    }
                }
                
            } catch (error) {
                showAlert('error', 'Error de conexión con el servidor');
                addLog('error', '❌ Error de conexión: ' + error.message);
                console.error('Import error:', error);
            } finally {
                isProcessing = false;
                document.getElementById('importBtn').disabled = false;
                updateProgress(0);
            }
        }
        
        function updateProgress(percent) {
            document.getElementById('progressFill').style.width = percent + '%';
        }
        
        function addLog(type, message) {
            const log = document.getElementById('processLog');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
        }
        
        function clearLog() {
            document.getElementById('processLog').innerHTML = '';
        }
        
        function showAlert(type, message) {
            // Hide all alerts
            document.querySelectorAll('.alert').forEach(alert => {
                alert.classList.remove('active');
            });
            
            // Show specific alert
            const alertId = type === 'success' ? 'alertSuccess' : 
                           type === 'error' ? 'alertError' : 'alertWarning';
            const messageId = type === 'success' ? 'successMessage' : 
                             type === 'error' ? 'errorMessage' : 'warningMessage';
            
            document.getElementById(messageId).textContent = message;
            document.getElementById(alertId).classList.add('active');
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                document.getElementById(alertId).classList.remove('active');
            }, 5000);
        }
        
        function resetForm() {
            selectedFile = null;
            csvData = [];
            document.getElementById('csvFile').value = '';
            document.getElementById('uploadZone').classList.remove('has-file');
            document.getElementById('fileInfo').classList.remove('active');
            document.getElementById('accountName').value = '';
            document.getElementById('accountLogin').value = '';
            document.getElementById('brokerName').value = '';
            document.getElementById('serverName').value = '';
            document.getElementById('accountCurrency').value = 'USD';
            document.getElementById('accountLeverage').value = '100';
            document.getElementById('totalRows').textContent = '0';
            document.getElementById('totalTrades').textContent = '0';
            document.getElementById('totalDeposits').textContent = '0';
            document.getElementById('totalWithdrawals').textContent = '0';
            document.getElementById('progressBar').classList.remove('active');
            document.getElementById('processLog').classList.remove('active');
            clearLog();
        }
        
        function downloadTemplate() {
            // Create CSV template
            const headers = [
                'ticket', 'time', 'type', 'symbol', 'volume', 'price',
                'sl', 'tp', 'commission', 'swap', 'profit', 'comment',
                'magic', 'entry', 'reason', 'position_id', 'order_id'
            ];
            
            const sampleData = [
                // Sample deposit
                ['100001', '2024-01-01 10:00:00', '2', '', '0', '0', '0', '0', '0', '0', '10000', 'Initial Deposit', '0', '0', '0', '0', '0'],
                // Sample trades
                ['100002', '2024-01-02 09:30:00', '0', 'EURUSD', '0.1', '1.09500', '1.09000', '1.10000', '-2', '0', '0', '', '12345', '0', '0', '1001', '2001'],
                ['100003', '2024-01-02 14:45:00', '1', 'EURUSD', '0.1', '1.09750', '0', '0', '-2', '-0.5', '25', '', '12345', '1', '0', '1001', '2002'],
                ['100004', '2024-01-03 11:20:00', '1', 'GBPUSD', '0.2', '1.26500', '1.26000', '1.27000', '-4', '0', '0', '', '12345', '0', '0', '1002', '2003'],
                ['100005', '2024-01-03 16:30:00', '0', 'GBPUSD', '0.2', '1.26800', '0', '0', '-4', '-1.2', '-60', '', '12345', '1', '0', '1002', '2004']
            ];
            
            let csvContent = headers.join(',') + '\n';
            sampleData.forEach(row => {
                csvContent += row.join(',') + '\n';
            });
            
            // Download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'elysium_import_template.csv';
            link.click();
            
            showAlert('success', 'Plantilla CSV descargada correctamente');
        }
        
        function copyColumnNames() {
            const columns = [
                'ticket', 'time', 'type', 'symbol', 'volume', 'price',
                'sl', 'tp', 'commission', 'swap', 'profit', 'comment',
                'magic', 'entry', 'reason', 'position_id', 'order_id'
            ].join(',');
            
            navigator.clipboard.writeText(columns).then(() => {
                showAlert('success', 'Nombres de columnas copiados al portapapeles');
            }).catch(() => {
                showAlert('error', 'Error al copiar al portapapeles');
            });
        }
        
        function showInstructions() {
            document.getElementById('instructionsModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        async function loadImportHistory() {
            try {
                const response = await fetch('api/get_import_history.php');
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    const historyHtml = result.data.map(item => `
                        <div style="background: rgba(255, 255, 255, 0.02); border-radius: 0.5rem; padding: 1rem; margin-bottom: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>${item.account_name}</strong> (#${item.account_login})
                                    <div style="font-size: 0.813rem; color: var(--text-muted);">
                                        ${item.broker} • ${item.records} registros • ${item.import_date}
                                    </div>
                                </div>
                                <span style="color: var(--success);">✅</span>
                            </div>
                        </div>
                    `).join('');
                    
                    document.getElementById('importHistory').innerHTML = historyHtml;
                }
            } catch (error) {
                console.error('Error loading history:', error);
            }
        }
        
        // Load history on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadImportHistory();
        });
        
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>