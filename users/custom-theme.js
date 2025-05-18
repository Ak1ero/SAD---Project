// Custom Theme Modal Functionality
let customThemeData = null;

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('customThemeModal');
    const customBtn = document.getElementById('customThemeBtn');
    const closeBtn = document.querySelector('.close-modal');
    const saveBtn = document.getElementById('saveCustomTheme');
    const customThemeOption = document.querySelector('.custom-theme-card');
    const customImageInput = document.getElementById('customThemeImage');
    
    // Open modal when customize button is clicked
    customBtn.addEventListener('click', function() {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    });
    
    // Close modal functions
    closeBtn.addEventListener('click', closeModal);
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
    
    // Image preview functionality
    customImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const preview = document.createElement('img');
                preview.id = 'imagePreview';
                preview.src = e.target.result;
                preview.classList.add('custom-theme-preview');
                
                // Remove any existing preview
                const existingPreview = document.getElementById('imagePreview');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                // Add the new preview
                customImageInput.parentNode.appendChild(preview);
                preview.style.display = 'block';
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Save custom theme data
    saveBtn.addEventListener('click', function() {
        const themeName = document.getElementById('customThemeName').value;
        const themeDescription = document.getElementById('customThemeDescription').value;
        const themeColors = document.getElementById('customThemeColors').value;
        const imageFile = customImageInput.files[0];
        
        if (!themeName || !themeDescription) {
            alert('Please enter at least a name and description for your custom theme.');
            return;
        }
        
        // Store custom theme data
        customThemeData = {
            name: themeName,
            description: themeDescription,
            colors: themeColors,
            imageFile: imageFile
        };
        
        // Update the custom theme option to show it's been customized
        customThemeOption.classList.add('selected');
        const customRadio = customThemeOption.querySelector('input[type="radio"]');
        customRadio.checked = true;
        
        // Add a visual indicator to the custom theme option
        const existingDetails = customThemeOption.querySelector('.custom-theme-details');
        if (existingDetails) {
            existingDetails.remove();
        }
        
        const detailsEl = document.createElement('div');
        detailsEl.classList.add('custom-theme-details');
        detailsEl.innerHTML = `
            <strong>${themeName}</strong>
            <p>${themeDescription.substring(0, 50)}${themeDescription.length > 50 ? '...' : ''}</p>
        `;
        
        customThemeOption.appendChild(detailsEl);
        
        // Close the modal
        closeModal();
        
        // Remove selected class from other themes
        document.querySelectorAll('.theme-option').forEach(theme => {
            if (theme !== customThemeOption) {
                theme.classList.remove('selected');
            }
        });
    });
}); 