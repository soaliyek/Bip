// Dynamic Flags Loader - Loads flags from database
let messageFlags = [];
let userFlags = [];
let flagsLoaded = false;

// Load flags on page load
async function loadFlags() {
    try {
        // Load message flags
        const messageResponse = await fetch('../../api/getflags.php?category=MESSAGE');
        const messageData = await messageResponse.json();
        if (messageData.success) {
            messageFlags = messageData.flags;
            console.log('[Flags] Loaded', messageFlags.length, 'message flags');
        }
        
        // Load user flags
        const userResponse = await fetch('../../api/getflags.php?category=USER');
        const userData = await userResponse.json();
        if (userData.success) {
            userFlags = userData.flags;
            console.log('[Flags] Loaded', userFlags.length, 'user flags');
        }
        
        flagsLoaded = true;
        
        // Populate UI elements if they exist
        populateReportModal();
        populateFlagButtons();
        
    } catch (err) {
        console.error('[Flags] Error loading flags:', err);
    }
}

// Populate report modal dropdown with message flags
function populateReportModal() {
    const select = document.querySelector('#reportModal select[name="flagType"]');
    if (!select) return;
    
    // Clear existing options except the first one ("Select a reason")
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Add flags as options
    messageFlags.forEach(flag => {
        const option = document.createElement('option');
        option.value = flag.flagTypeID;
        option.textContent = flag.displayName;
        option.title = flag.description || '';
        
        // Add severity indicator
        /*
        if (flag.severity === 'CRITICAL' || flag.severity === 'HIGH') {
            option.textContent += ' ⚠️';
            //option.textContent += ' ⚠️';
        }
        */
        
        select.appendChild(option);
    });
    
    console.log('[Flags] Populated report modal with', messageFlags.length, 'flags');
}

// Populate user flag buttons
function populateFlagButtons() {
    const container = document.querySelector('.user-flags-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    // Group flags by sentiment
    const positiveFlags = userFlags.filter(f => f.sentiment === 'POSITIVE');
    const negativeFlags = userFlags.filter(f => f.sentiment === 'NEGATIVE');
    
    // Create positive section
    if (positiveFlags.length > 0) {
        const positiveSection = document.createElement('div');
        positiveSection.className = 'flag-section positive-flags';
        positiveSection.innerHTML = '<h4>👍 Positive Feedback</h4>';
        
        positiveFlags.forEach(flag => {
            const button = createFlagButton(flag, 'positive');
            positiveSection.appendChild(button);
        });
        
        container.appendChild(positiveSection);
    }
    
    // Create negative section
    if (negativeFlags.length > 0) {
        const negativeSection = document.createElement('div');
        negativeSection.className = 'flag-section negative-flags';
        negativeSection.innerHTML = '<h4>⚠️ Report Issues</h4>';
        
        negativeFlags.forEach(flag => {
            const button = createFlagButton(flag, 'negative');
            negativeSection.appendChild(button);
        });
        
        container.appendChild(negativeSection);
    }
    
    console.log('[Flags] Populated user flag buttons');
}

// Create a flag button element
function createFlagButton(flag, sentiment) {
    const button = document.createElement('button');
    button.className = `flag-btn flag-btn-${sentiment}`;
    button.dataset.flagId = flag.flagTypeID;
    button.dataset.flagCode = flag.code;
    button.textContent = flag.displayName;
    button.title = flag.description || '';
    
    button.addEventListener('click', function() {
        handleUserFlag(flag.flagTypeID, flag.code, flag.displayName);
    });
    
    return button;
}

// Handle user flag submission
function handleUserFlag(flagTypeID, flagCode, displayName) {
    if (!window.interlocutorID) {
        alert('No user selected');
        return;
    }
    
    if (confirm(`Flag this user as "${displayName}"?`)) {
        fetch('../../api/rateuser.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                targetUserID: window.interlocutorID,
                flagTypeID: flagTypeID
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`User flagged as "${displayName}"!`);
                
                // Disable the button
                const button = document.querySelector(`[data-flag-id="${flagTypeID}"]`);
                if (button) {
                    button.disabled = true;
                    button.textContent += ' ✓';
                    button.style.opacity = '0.5';
                }
                
                // If positive flag was added to safelist
                if (['HELPFUL', 'SAFE', 'EMPATHETIC'].includes(flagCode)) {
                    alert('User has been added to your safelist!');
                }
            } else {
                alert('Failed to flag user: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('[Flags] Error flagging user:', err);
            alert('Failed to flag user. Please try again.');
        });
    }
}

// Update report form handler to use flagTypeID
function setupReportForm() {
    const reportForm = document.getElementById('reportForm');
    if (!reportForm) return;
    
    reportForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            messageID: formData.get('messageID'),
            flagTypeID: parseInt(formData.get('flagType')),
            reason: formData.get('reason')
        };
        
        if (!data.flagTypeID) {
            alert('Please select a reason for reporting');
            return;
        }
        
        fetch('../../api/flagmessage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Message reported successfully');
                const modal = document.getElementById('reportModal');
                if (modal) modal.style.display = 'none';
                e.target.reset();
            } else {
                alert('Failed to report: ' + (result.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('[Flags] Error reporting message:', err);
            alert('Failed to report message');
        });
    });
    
    console.log('[Flags] Report form handler set up');
}

// Helper functions
function isFlagsLoaded() {
    return flagsLoaded;
}

function getFlagDisplayName(flagTypeID) {
    const allFlags = [...messageFlags, ...userFlags];
    const flag = allFlags.find(f => f.flagTypeID === flagTypeID);
    return flag ? flag.displayName : 'Unknown';
}

function getFlagByCode(code) {
    const allFlags = [...messageFlags, ...userFlags];
    return allFlags.find(f => f.code === code);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        loadFlags();
        setupReportForm();
    });
} else {
    loadFlags();
    setupReportForm();
}

// Export for use in other scripts
window.FlagsLoader = {
    loadFlags,
    isFlagsLoaded,
    getFlagDisplayName,
    getFlagByCode,
    messageFlags: () => messageFlags,
    userFlags: () => userFlags
};

console.log('[Flags] Flags loader initialized');
