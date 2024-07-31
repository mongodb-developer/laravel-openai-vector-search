<template>
    <div class="animated-loading">
      <div class="emoji-container">
        {{ currentEmoji }}
      </div>
      <div class="activity-name">
        {{ currentActivity }}
      </div>
    </div>
  </template>
  
  <script>
  export default {
    name: 'AnimatedLoading',
    props: {
      activities: {
        type: Array,
        required: true
      }
    },
    data() {
      return {
        emojis: ['🌴', '🏰', '🍽️', '🚶', '🏛️', '🎭', '🏔️', '🌊', '🚢', '✈️'],
        currentEmojiIndex: 0,
        currentActivityIndex: 0,
        intervalId: null
      }
    },
    computed: {
      currentEmoji() {
        return this.emojis[this.currentEmojiIndex]
      },
      currentActivity() {
        return `Should we: ${this.activities[this.currentActivityIndex]}` || 'Planning your trip...'
      }
    },
    mounted() {
      this.startAnimation()
    },
    beforeUnmount() {
      this.stopAnimation()
    },
    methods: {
      startAnimation() {
        this.intervalId = setInterval(() => {
          this.currentEmojiIndex = (this.currentEmojiIndex + 1) % this.emojis.length
          this.currentActivityIndex = (this.currentActivityIndex + 1) % this.activities.length
        }, 1000) // Change every second
      },
      stopAnimation() {
        if (this.intervalId) {
          clearInterval(this.intervalId)
        }
      }
    }
  }
  </script>
  
  <style scoped>
  .animated-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
  }
  
  .emoji-container {
    font-size: 4rem;
    margin-bottom: 1rem;
  }
  
  .activity-name {
    font-size: 1.2rem;
    color: #4a5568;
  }
  </style>