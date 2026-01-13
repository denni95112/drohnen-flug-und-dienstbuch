// Documents JavaScript with API integration

// Get CSRF token
function getCsrfToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

// Show message
function showMessage(message, type = 'success') {
    const container = document.getElementById(type === 'success' ? 'message-container' : 'error-container');
    if (container) {
        container.innerHTML = `<p class="${type}">${escapeHtml(message)}</p>`;
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    }
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('de-DE');
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Store all documents for filtering
let allDocuments = [];

// Filter and display documents
function displayDocuments(documents) {
    const tbody = document.getElementById('documents-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (documents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5">Keine Dokumente gefunden</td></tr>';
    } else {
        documents.forEach(doc => {
            const row = document.createElement('tr');
            const basePath = window.basePath || '';
            
            row.innerHTML = `
                <td>${escapeHtml(doc.original_filename)}</td>
                <td>${escapeHtml(doc.description || '-')}</td>
                <td class="file-size">${formatFileSize(doc.file_size)}</td>
                <td>${formatDate(doc.uploaded_at)}</td>
                <td>
                    <div class="action-buttons">
                        <a href="${basePath}pages/documents.php?preview_file=true&document_id=${doc.id}" target="_blank" class="btn-preview">Vorschau</a>
                        <a href="${basePath}pages/documents.php?download_file=true&document_id=${doc.id}" class="btn-download">Herunterladen</a>
                        ${window.isAdmin ? `
                            <button type="button" class="btn-delete delete-document-btn" data-document-id="${doc.id}">
                                Löschen
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
        
        // Attach event listeners
        attachEventListeners();
    }
}

// Filter documents based on search query
function filterDocuments(searchQuery) {
    if (!searchQuery || searchQuery.trim() === '') {
        displayDocuments(allDocuments);
        return;
    }
    
    const query = searchQuery.toLowerCase().trim();
    const filtered = allDocuments.filter(doc => {
        const filename = (doc.original_filename || '').toLowerCase();
        const description = (doc.description || '').toLowerCase();
        return filename.includes(query) || description.includes(query);
    });
    
    displayDocuments(filtered);
}

// Fetch documents from API
async function fetchDocuments() {
    const tbody = document.getElementById('documents-tbody');
    const loading = document.getElementById('loading-indicator');
    
    if (loading) loading.style.display = 'block';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/documents.php?action=list`);
        const data = await response.json();
        
        if (data.success) {
            // Store all documents for filtering
            allDocuments = data.data.documents;
            
            // Get current search query
            const searchInput = document.getElementById('search-documents');
            const searchQuery = searchInput ? searchInput.value : '';
            
            // Display filtered documents
            filterDocuments(searchQuery);
        } else {
            showMessage(data.error || 'Fehler beim Laden der Dokumente', 'error');
        }
    } catch (error) {
        console.error('Error fetching documents:', error);
        showMessage('Fehler beim Laden der Dokumente: ' + error.message, 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

// Upload document
async function uploadDocument(formData) {
    const submitBtn = document.querySelector('#upload-document-form button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird hochgeladen...';
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/documents.php?action=upload`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Dokument erfolgreich hochgeladen und verschlüsselt', 'success');
            document.getElementById('upload-document-form').reset();
            await fetchDocuments();
        } else {
            showMessage(data.error || 'Fehler beim Hochladen des Dokuments', 'error');
        }
    } catch (error) {
        console.error('Error uploading document:', error);
        showMessage('Fehler beim Hochladen des Dokuments: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// Delete document
async function deleteDocument(documentId) {
    if (!confirm('Dokument wirklich löschen?')) {
        return;
    }
    
    try {
        const basePath = window.basePath || '';
        const response = await fetch(`${basePath}api/documents.php?id=${documentId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: getCsrfToken()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Dokument erfolgreich gelöscht', 'success');
            await fetchDocuments();
        } else {
            showMessage(data.error || 'Fehler beim Löschen des Dokuments', 'error');
        }
    } catch (error) {
        console.error('Error deleting document:', error);
        showMessage('Fehler beim Löschen des Dokuments: ' + error.message, 'error');
    }
}

// Attach event listeners
function attachEventListeners() {
    // Delete buttons
    document.querySelectorAll('.delete-document-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const documentId = this.getAttribute('data-document-id');
            deleteDocument(documentId);
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Fetch documents on load
    fetchDocuments();
    
    // Search input handler
    const searchInput = document.getElementById('search-documents');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterDocuments(this.value);
        });
    }
    
    // Upload form handler
    const uploadForm = document.getElementById('upload-document-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('document_file');
            const file = fileInput.files[0];
            
            if (!file) {
                showMessage('Bitte wählen Sie eine PDF-Datei aus', 'error');
                return;
            }
            
            // Validate file type
            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                showMessage('Nur PDF-Dateien sind erlaubt', 'error');
                return;
            }
            
            // Create FormData
            const formData = new FormData();
            formData.append('document_file', file);
            formData.append('csrf_token', getCsrfToken());
            
            const description = document.getElementById('document_description').value;
            if (description) {
                formData.append('description', description);
            }
            
            await uploadDocument(formData);
        });
    }
});
