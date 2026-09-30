class AnimatedDetails {
  // The default constructor for each 
  constructor(el) {
    // Store the <details> element
    this.el = el;
    // Store the <summary> element
    this.summary = el.querySelector('summary');
    // Store the <div class="content"> element
    this.content = el.querySelector('.gmu-emergency-alert-content');
  
    // Store the animation object (so we can cancel it, if needed)
    this.animation = null;
    // Store if the element is closing
    this.isClosing = false;
    // Store if the element is expanding
    this.isExpanding = false;
    // Detect user clicks on the summary element
    this.el.addEventListener('click', (e) => this.onClick(e));  
  }

  // Function called when user clicks on the summary
  onClick(e) {
    if(e.target.nodeName.toLowerCase() == 'summary'){
      // Stop default behaviour from the browser
      //e.preventDefault();
      // Add an overflow on the <details> to avoid content overflowing
      console.log(this);
      this.el.style.overflow = 'hidden';
      // Check if the element is being closed or is already closed
      if (this.isClosing || !this.el.open) {
          this.open();
      // Check if the element is being openned or is already open
      } else if (this.isExpanding || this.el.open) {
          this.shrink();
      }
    }
  }

  // Function called to close the content with an animation
  shrink() {
    // Set the element as "being closed"
    this.isClosing = true;

    // Store the current height of the element
    const startHeight = `${this.el.offsetHeight}px`;
    // Calculate the height of the summary
    const endHeight = `${this.summary.offsetHeight}px`;

    // If there is already an animation running
    if (this.animation) {
    // Cancel the current animation
    this.animation.cancel();
    }

    // Start a WAAPI animation
    this.animation = this.el.animate({
    // Set the keyframes from the startHeight to endHeight
    height: [startHeight, endHeight]
    }, {
    // If the duration is too slow or fast, you can change it here
    duration: 200,
    // You can also change the ease of the animation
    easing: 'ease-out'
    });

    // When the animation is complete, call onAnimationFinish()
    this.animation.onfinish = () => this.onAnimationFinish(false);
    // If the animation is cancelled, isClosing variable is set to false
    this.animation.oncancel = () => this.isClosing = false;    
  }

  // Function called to open the element after click
  open() {
    // Apply a fixed height on the element
    this.el.style.height = `${this.el.offsetHeight}px`;
    // Force the [open] attribute on the details element
    this.el.open = true;
    // Wait for the next frame to call the expand function
    window.requestAnimationFrame(() => this.expand());    
  }

  // Function called to expand the content with an animation
  expand() {
    // Set the element as "being expanding"
    this.isExpanding = true;
    // Get the current fixed height of the element
    const startHeight = `${this.el.offsetHeight}px`;
    // Calculate the open height of the element (summary height + content height)
    const endHeight = `${this.summary.offsetHeight + this.content.offsetHeight}px`;

    // If there is already an animation running
    if (this.animation) {
        // Cancel the current animation
        this.animation.cancel();
    }

    // Start a WAAPI animation
    this.animation = this.el.animate({
        // Set the keyframes from the startHeight to endHeight
        height: [startHeight, endHeight]
    }, {
        // If the duration is too slow of fast, you can change it here
        duration: 200,
        // You can also change the ease of the animation
        easing: 'ease-out'
    });
    // When the animation is complete, call onAnimationFinish()
    this.animation.onfinish = () => this.onAnimationFinish(true);
    // If the animation is cancelled, isExpanding variable is set to false
    this.animation.oncancel = () => this.isExpanding = false;    
  }

  // Callback when the shrink or expand animations are done
  onAnimationFinish(open) {
    // Set the open attribute based on the parameter
    this.el.open = open;
    // Clear the stored animation
    this.animation = null;
    // Reset isClosing & isExpanding
    this.isClosing = false;
    this.isExpanding = false;
    // Remove the overflow hidden and the fixed height
    this.el.style.height = this.el.style.overflow = '';
  }
}

document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById(
        'gmu-was-emergencyalerts-placeholder'
    );

    if (!container || typeof gmuEmergencyAlerts === 'undefined') {
        return;
    }

    const requestBody = new URLSearchParams({
        action: gmuEmergencyAlerts.action
    });

    try {
        const response = await fetch(gmuEmergencyAlerts.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: requestBody.toString()
        });

        if (!response.ok) {
            throw new Error(
                `Alert request returned HTTP ${response.status}`
            );
        }

        const result = await response.json();

        if (
            !result.success ||
            !result.data ||
            !result.data.active ||
            !result.data.html
        ) {
            container.replaceChildren();
            container.hidden = true;
            return;
        }

        container.innerHTML = result.data.html;
        container.hidden = false;
    } catch (error) {
        container.replaceChildren();
        container.hidden = true;

        console.error(
            'Unable to retrieve the emergency alert.',
            error
        );
    }

    document.querySelectorAll('details.gmu-emergency-alert-details').forEach((el) => {
      new AnimatedDetails(el);
    });

  });

