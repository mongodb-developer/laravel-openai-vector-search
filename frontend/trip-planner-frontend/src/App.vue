<template>
  <div class="min-h-screen bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
      <h1 class="text-4xl font-bold text-center text-indigo-600 mb-8">Trip Planner</h1>
      
      <!-- Search Bar -->
      <div class="mb-8">
        <input v-model="searchQuery" @input="debouncedSearch" placeholder="Search for places or activities" 
               class="w-full p-4 border border-gray-300 rounded-lg shadow-sm text-lg" />
      </div>

      <!-- Search Results -->
      <div v-if="Object.keys(searchResults).length > 0" class="mb-8 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold p-4 border-b">Search Results</h2>
    <div v-for="(cityData, city) in searchResults" :key="city" class="p-4 border-b last:border-b-0">
      <div class="flex justify-between items-center mb-2">
        <h3 class="text-xl font-semibold">{{ city }}</h3>
        <button 
          @click="addCityFromSearch(city)" 
          class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-1 px-3 rounded text-sm"
          :disabled="selectedCities.includes(city)"
        >
          {{ selectedCities.includes(city) ? 'Added' : 'Add City' }}
        </button>
      </div>
      <ul class="list-none pl-0">
        <li v-for="poi in cityData.pois" :key="poi._id" class="text-sm text-gray-600 mb-2">
          <div class="flex justify-between items-center">
            <span>{{ poi.name }}</span>
            <span class="text-xs text-gray-500">Score: {{ formatScore(poi.score) }}</span>
          </div>
          <p class="text-xs text-gray-500">{{ poi.description }}</p>
        </li>
      </ul>
    </div>
  </div>

      <!-- City Tiles -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="city in supportedCities" :key="city" class="bg-white rounded-lg shadow-md overflow-hidden">
          <div class="p-6">
            <h2 class="text-2xl font-bold text-indigo-600 mb-4">{{ city }}</h2>
            <div v-if="topActivities[city]">
              <h3 class="text-lg font-semibold mb-2">Top Activities:</h3>
              <ul class="list-disc pl-5">
                <li v-for="activity in topActivities[city]" :key="activity" class="mb-1">
                  {{ activity }}
                </li>
              </ul>
            </div>
            <div v-else class="text-gray-500">
              Loading top activities...
            </div>
            <button @click="selectCity(city)" class="mt-4 bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded">
              Select City
            </button>
          </div>
        </div>
      </div>

      <!-- Selected Cities and Trip Planning -->
      <div v-if="selectedCities.length > 0" class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold mb-4">Your Trip</h2>
        <div class="flex flex-wrap gap-2 mb-4">
          <div v-for="(city, index) in selectedCities" :key="index" class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full flex items-center">
            {{ city }}
            <button @click="removeCity(index)" class="ml-2 text-indigo-600 hover:text-indigo-800">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <input v-model="duration" type="number" placeholder="Duration (days)" class="p-2 border border-gray-300 rounded w-32" /> Days
          <button @click="planTrip" :disabled="loading" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded disabled:opacity-50">
            Plan Trip
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <AnimatedLoading v-if="loading" :activities="loadingActivities" />

      <!-- Error Message -->
      <div v-if="error" class="mt-8 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ error }}</span>
      </div>

      <!-- Trip Plan Display -->
      <div v-if="tripPlan" class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold mb-4">Your Custom Trip Plan</h2>
        <h3 class="text-xl font-semibold mb-2">{{ tripPlan.destination.map(
          dest => dest.city + ', ' + dest.country
          
        ) }}</h3>
        
        <div v-for="(day, index) in tripPlan.itinerary" :key="index" class="mb-6">
          <h4 class="text-lg font-semibold mb-2">Day {{ day.day }}</h4>
          <ul class="space-y-2">
            <li v-for="(activity, actIndex) in day.activities" :key="actIndex" class="bg-gray-50 p-3 rounded">
  <div>
    <span class="font-bold">{{ activity.time }}:</span> {{ activity.activity }}
    <span class="text-sm text-gray-600 ml-2">({{ activity.duration }})</span>
    <span v-if="activity.src_airport_code" class="text-sm text-gray-600 ml-2">
      - {{activity.src_airport_code}} to {{activity.dest_airport_code}}
    </span>
  </div>
  
  <div v-if="activity.src_airport_code && activity.dest_airport_code" class="mt-2">
    <button @click="toggleFlights(day.day, actIndex)" class="text-sm text-indigo-600 hover:text-indigo-800">
      {{ activity.showFlights ? 'Hide Flights' : 'Show Available Flights' }}
    </button>
    <transition name="fade">
      <div v-if="activity.showFlights" class="mt-2">
        <ul class="space-y-2">
          <li v-for="flight in getFlightsForActivity(activity)" :key="flight._id.$oid" class="bg-white p-2 rounded shadow-sm">
            <div class="font-semibold">{{ flight.airline.name }} ({{ flight.airline.iata }})</div>
            <div class="text-sm">
              From: {{ flight.src_airport }} To: {{ flight.dst_airport }}
              | Stops: {{ flight.stops }}
              | Aircraft: {{ flight.airplane }}
            </div>
          </li>
        </ul>
        <div v-if="getFlightsForActivity(activity).length === 0" class="text-sm text-gray-600">
          No flights available for this route.
        </div>
      </div>
    </transition>
  </div>
</li>
          </ul>
        </div>

        <h4 class="text-lg font-semibold mt-6 mb-2">Points of Interest</h4>
        <ul class="space-y-2">
          <li v-for="poi in tripPlan.pointsOfInterest" :key="poi.name" class="bg-gray-50 p-3 rounded">
            <span class="font-bold">{{ poi.name }}</span>
            <p class="text-sm text-gray-600">{{ poi.description }}</p>
            <p class="text-sm">Rating: {{ poi.rating }}/5</p>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { reactive } from 'vue'

import AnimatedLoading from './components/AnimatedLoading.vue'

export default {
  components: {
    AnimatedLoading
  },
  data() {
    return {
      searchQuery: '',
      searchResults: [],
      supportedCities: [],
      selectedCities: [],
      topActivities: reactive({}),
      flights: [],
      duration: 3,
      loading: false,
      error: null,
      tripPlan: null,
      loadingActivities: []
    }
  },
  mounted() {
    this.fetchSupportedCities()
  },
  methods: {
    toggleFlights(day, activityIndex) {
      if (this.tripPlan) {
        const activity = this.tripPlan.itinerary[day - 1].activities[activityIndex]
        if (!Object.hasOwn(activity, 'showFlights')) {
          this.$nextTick(() => {
            activity.showFlights = true
          })
        } else {
          activity.showFlights = !activity.showFlights
        }
      }
    },

    getFlightsForActivity(activity) {
      return this.flights.filter(flight => 
        flight.src_airport === activity.src_airport_code && 
        flight.dst_airport === activity.dest_airport_code
      )
    },

    async fetchSupportedCities() {
      try {
        const response = await axios.get('http://localhost:8000/api/cities')
        this.supportedCities = response.data.map(city => city[0])
        
        this.supportedCities.forEach(this.fetchTopActivities)
      } catch (err) {
        console.error('Error fetching supported cities:', err)
        this.error = 'Failed to fetch supported cities'
      }
    },
    debouncedSearch() {
      // Clear any existing timeout
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout)
      }

      // Set a new timeout
      this.searchTimeout = setTimeout(() => {
        this.searchPlaces()
      }, 300) // 300ms delay
    },

    async fetchTopActivities(city) {
      try {
        const response = await axios.get(`http://localhost:8000/api/cities/top-points`, {
          params: { city }
        })
        // Use direct assignment for reactive update
        this.topActivities[city] = response.data.context.map(poi => poi.name)
      } catch (err) {
        console.error(`Error fetching top activities for ${city}:`, err)
      }
    },
    async searchPlaces() {
      if (this.searchQuery.length < 2) {
        this.searchResults = {}
        return
      }

      try {
        const response = await axios.get(`http://localhost:8000/api/cities/search`, {
          params: { city: this.searchQuery }
        })
        
        // Sort results by score and organize by city
        const sortedResults = response.data.sort((a, b) => b.score - a.score)
        
        this.searchResults = sortedResults.reduce((acc, poi) => {
          const city = poi.location.city
          if (!acc[city]) {
            acc[city] = { pois: [], maxScore: 0 }
          }
          acc[city].pois.push(poi)
          acc[city].maxScore = Math.max(acc[city].maxScore, poi.score)
          return acc
        }, {})

        // Sort cities by their highest score
        this.searchResults = Object.fromEntries(
          Object.entries(this.searchResults).sort((a, b) => b[1].maxScore - a[1].maxScore)
        )
      } catch (err) {
        console.error('Error searching places:', err)
      }
    },

    formatScore(score) {
      // Adjust this function based on the range of your scores
      return score.toFixed(2)
    },

    addCityFromSearch(city) {
      if (!this.selectedCities.includes(city)) {
        this.selectedCities.push(city)
        this.fetchTopActivities(city)
      }
    },
    selectCity(city) {
      if (!this.selectedCities.includes(city)) {
        this.selectedCities.push(city)
      }
    },
    removeCity(index) {
      this.selectedCities.splice(index, 1)
    },
    async planTrip() {
      if (this.selectedCities.length === 0 || this.duration <= 0) {
        this.error = 'Please select at least one city and enter a valid duration.'
        return
      }

      this.loading = true
      this.error = null
      this.tripPlan = null
      
      //for (city in this.selectedCities) {
      for (let city of this.selectedCities) {
        
        
        // concat all activities
        this.loadingActivities = this.loadingActivities.concat(this.topActivities[city])
      
        
      
      }
      try {
        console.log('Selected cities:', this.selectedCities)
        const response = await axios.post('http://localhost:8000/api/cities/plan-trip', {
          cities: this.selectedCities,
          days : this.duration
        
        })
        

        
        this.tripPlan = response.data.suggestion.tripPlan
        this.flights = response.data.flights

        // Initialize showFlights property for each activity
        this.tripPlan.itinerary.forEach(day => {
          day.activities.forEach(activity => {
            activity.showFlights = false
          })
        });
        
      } catch (err) {
        this.error = 'Failed to fetch trip plan. Please try again.'
        console.error('Error fetching trip plan:', err)
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}
</style>