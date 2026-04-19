// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if(!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    // Price formatting
    const priceInputs = document.querySelectorAll('input[type="number"][name="base_price"]');
    priceInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if(this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });

    // Category type change - could be used for dynamic filtering
    const categoryTypeSelect = document.getElementById('category_type');
    if(categoryTypeSelect) {
        categoryTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            // You can add additional logic here if needed
            console.log('Selected category type:', selectedType);
        });
    }

    // Search input debouncing
    const searchInput = document.querySelector('input[name="search"]');
    if(searchInput) {
        let timeout = null;
        searchInput.addEventListener('keyup', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});

// Function to preview player details
function previewPlayer() {
    const name = document.getElementById('player_name')?.value;
    const age = document.getElementById('age')?.value;
    const role = document.getElementById('playing_role')?.value;
    const price = document.getElementById('base_price')?.value;
    
    if(name && age && role && price) {
        const previewHtml = `
            <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                        background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); 
                        z-index: 1000; max-width: 400px; width: 90%;">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Player Preview</h3>
                <p><strong>Name:</strong> ${name}</p>
                <p><strong>Age:</strong> ${age}</p>
                <p><strong>Role:</strong> ${role}</p>
                <p><strong>Base Price:</strong> ₹${parseFloat(price).toFixed(2)}</p>
                <button onclick="this.parentElement.remove()" 
                        style="margin-top: 15px; padding: 8px 16px; background: #3498db; color: white; 
                               border: none; border-radius: 4px; cursor: pointer;">Close</button>
            </div>
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); 
                       z-index: 999;" onclick="this.previousElementSibling.remove(); this.remove();"></div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', previewHtml);
    } else {
        alert('Please fill all fields to preview');
    }
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save form
    if(e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const form = document.querySelector('form');
        if(form) {
            form.submit();
        }
    }
    
    // Escape key to close modals
    if(e.key === 'Escape') {
        const modals = document.querySelectorAll('[style*="position: fixed"]');
        modals.forEach(modal => modal.remove());
    }
});