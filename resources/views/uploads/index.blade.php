<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>File Upload System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-3xl font-bold mb-8 text-gray-800">CSV File Upload System</h1>
            
            <!-- Connection Status -->
            <div id="connectionStatus" class="mb-4 p-3 rounded-lg bg-gray-200 text-gray-700 text-sm">
                WebSocket: <span id="wsStatus">Connecting...</span>
            </div>
            
            <!-- Upload Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <input 
                        type="file" 
                        id="fileInput" 
                        accept=".csv"
                        class="hidden"
                    >
                    <label for="fileInput" class="cursor-pointer">
                        <div class="mb-4">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="text-gray-600 mb-2">Select file/Drag and drop</p>
                        <p class="text-sm text-gray-500">CSV files only</p>
                    </label>
                    <button 
                        id="uploadBtn"
                        class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled
                    >
                        Upload File
                    </button>
                </div>
                <div id="selectedFile" class="mt-4 text-sm text-gray-600 hidden"></div>
                <div id="uploadProgress" class="mt-4 hidden">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <!-- Upload History -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Time
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    File Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody id="uploadsTable" class="bg-white divide-y divide-gray-200">
                            <!-- Uploads will be inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const uploadBtn = document.getElementById('uploadBtn');
        const selectedFile = document.getElementById('selectedFile');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const uploadsTable = document.getElementById('uploadsTable');
        const wsStatus = document.getElementById('wsStatus');
        const connectionStatus = document.getElementById('connectionStatus');

        let selectedFileObj = null;

        // File selection
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                selectedFileObj = file;
                selectedFile.textContent = `Selected: ${file.name}`;
                selectedFile.classList.remove('hidden');
                uploadBtn.disabled = false;
            }
        });

        // Drag and drop
        const dropZone = document.querySelector('.border-dashed');
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-blue-500', 'bg-blue-50');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-500', 'bg-blue-50');
            
            const file = e.dataTransfer.files[0];
            if (file && file.name.endsWith('.csv')) {
                selectedFileObj = file;
                fileInput.files = e.dataTransfer.files;
                selectedFile.textContent = `Selected: ${file.name}`;
                selectedFile.classList.remove('hidden');
                uploadBtn.disabled = false;
            }
        });

        // Upload file
        uploadBtn.addEventListener('click', async () => {
            if (!selectedFileObj) return;

            const formData = new FormData();
            formData.append('file', selectedFileObj);

            uploadBtn.disabled = true;
            uploadProgress.classList.remove('hidden');
            progressBar.style.width = '0%';

            try {
                const response = await fetch('/api/uploads', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    progressBar.style.width = '100%';
                    setTimeout(() => {
                        uploadProgress.classList.add('hidden');
                        progressBar.style.width = '0%';
                    }, 1000);

                    // Reset form
                    fileInput.value = '';
                    selectedFileObj = null;
                    selectedFile.classList.add('hidden');
                    uploadBtn.disabled = true;

                    // **Immediately insert new upload into the table**
                    const newUpload = data.upload;
                    const rowHtml = `
                        <tr data-upload-id="${newUpload.id}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${newUpload.uploaded_at}
                                <div class="text-xs text-gray-500">${newUpload.time_ago}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${newUpload.file_name}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                ${getStatusBadge(newUpload.status)}
                            </td>
                        </tr>
                    `;
                    uploadsTable.insertAdjacentHTML('afterbegin', rowHtml);

                } else {
                    alert(data.message || 'Upload failed');
                    uploadBtn.disabled = false;
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload failed');
                uploadBtn.disabled = false;
            }
        });

        // Load uploads
        async function loadUploads() {
            try {
                const response = await fetch('/api/uploads');
                const data = await response.json();
                
                uploadsTable.innerHTML = data.data.map(upload => `
                    <tr data-upload-id="${upload.id}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${upload.uploaded_at}
                            <div class="text-xs text-gray-500">${upload.time_ago}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${upload.file_name}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            ${getStatusBadge(upload.status)}
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                console.error('Error loading uploads:', error);
            }
        }

        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>',
                'processing': '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Processing</span>',
                'completed': '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Completed</span>',
                'failed': '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>'
            };
            return badges[status] || status;
        }

        // Initial load
        loadUploads();

        // WebSocket connection with proper initialization
        function initializeWebSocket() {
            // Wait for Echo to be fully initialized
            const checkEcho = setInterval(() => {
                if (window.Echo) {
                    clearInterval(checkEcho);
                    console.log('Echo initialized:', window.Echo);
                    
                    // Update connection status
                    wsStatus.textContent = 'Connected';
                    connectionStatus.classList.remove('bg-gray-200', 'text-gray-700');
                    connectionStatus.classList.add('bg-green-100', 'text-green-800');
                    
                    // Subscribe to the uploads channel
                    const channel = window.Echo.channel('uploads');
                    
                    console.log('Subscribed to uploads channel');
                    
                    // Listen for the UploadStatusChanged event
                    channel.listen('.UploadStatusChanged', (e) => {
                        console.log('Received UploadStatusChanged event:', e);
                        
                        const uploadData = e.upload;
                        const row = document.querySelector(`tr[data-upload-id="${uploadData.id}"]`);
                        
                        if (row) {
                            // Update existing row
                            const statusCell = row.querySelector('td:last-child');
                            statusCell.innerHTML = getStatusBadge(uploadData.status);
                            
                            // Add flash animation
                            row.classList.add('bg-yellow-50');
                            setTimeout(() => {
                                row.classList.remove('bg-yellow-50');
                            }, 2000);
                        } else {
                            // Row doesn't exist, reload the table
                            console.log('Upload not found in table, reloading...');
                            loadUploads();
                        }
                    });
                    
                    // Handle connection errors
                    if (window.Echo.connector && window.Echo.connector.pusher) {
                        window.Echo.connector.pusher.connection.bind('error', (err) => {
                            console.error('WebSocket error:', err);
                            wsStatus.textContent = 'Error';
                            connectionStatus.classList.remove('bg-green-100', 'text-green-800');
                            connectionStatus.classList.add('bg-red-100', 'text-red-800');
                        });
                        
                        window.Echo.connector.pusher.connection.bind('disconnected', () => {
                            console.warn('WebSocket disconnected');
                            wsStatus.textContent = 'Disconnected';
                            connectionStatus.classList.remove('bg-green-100', 'text-green-800');
                            connectionStatus.classList.add('bg-yellow-100', 'text-yellow-800');
                        });
                        
                        window.Echo.connector.pusher.connection.bind('connected', () => {
                            console.log('WebSocket connected');
                            wsStatus.textContent = 'Connected';
                            connectionStatus.classList.remove('bg-yellow-100', 'text-yellow-800', 'bg-red-100', 'text-red-800');
                            connectionStatus.classList.add('bg-green-100', 'text-green-800');
                        });
                    }
                }
            }, 100);
            
            // Timeout after 10 seconds
            setTimeout(() => {
                clearInterval(checkEcho);
                if (!window.Echo) {
                    console.error('Echo failed to initialize');
                    wsStatus.textContent = 'Failed to connect';
                    connectionStatus.classList.remove('bg-gray-200', 'text-gray-700');
                    connectionStatus.classList.add('bg-red-100', 'text-red-800');
                }
            }, 10000);
        }

        // Initialize WebSocket when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeWebSocket);
        } else {
            initializeWebSocket();
        }
    </script>
</body>
</html>