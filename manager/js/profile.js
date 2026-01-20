// Profile Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeProfilePage();
});

function initializeProfilePage() {
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
            if (section) {
                toggleEditMode(section);
            }
        });
    });
}

function toggleEditMode(section) {
    const card = document.querySelector(`#${section}-${section} .card-body`);
    if (!card) return;
    
    const isEditing = card.classList.contains('edit-mode-active');
    
    if (!isEditing) {
        // Enable edit mode
        const items = card.querySelectorAll('.info-item');
        items.forEach(item => {
            const label = item.querySelector('label');
            const value = item.querySelector('span');
            
            if (value && label) {
                const inputValue = value.textContent.trim();
                const fieldName = label.textContent.toLowerCase().replace(/\s+/g, '_');
                
                const input = document.createElement('input');
                input.type = 'text';
                input.value = inputValue;
                input.className = 'edit-input';
                input.dataset.field = fieldName;
                
                value.style.display = 'none';
                item.appendChild(input);
            }
        });
        
        card.classList.add('edit-mode-active');
    } else {
        // Disable edit mode
        const inputs = card.querySelectorAll('.edit-input');
        inputs.forEach(input => {
            input.remove();
        });
        
        const spans = card.querySelectorAll('span');
        spans.forEach(span => {
            span.style.display = '';
        });
        
        card.classList.remove('edit-mode-active');
    }
}

// Profile utilities
function handleProfileImageUpload(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatar = document.querySelector('.profile-avatar-xl');
            if (avatar) {
                const img = document.createElement('img');
                img.src = e.target.result;
                avatar.innerHTML = '';
                avatar.appendChild(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Smooth animations
window.addEventListener('load', function() {
    document.querySelectorAll('.profile-card').forEach((card, index) => {
        card.style.animation = `fadeInUp 0.5s ease forwards`;
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Add CSS for animations if not already present
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .edit-input {
        padding: 0.6rem;
        border: 2px solid #d41c1c;
        border-radius: 6px;
        font-size: 0.95rem;
    }
    
    .edit-input:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(212, 28, 28, 0.1);
    }
`;
document.head.appendChild(style);
