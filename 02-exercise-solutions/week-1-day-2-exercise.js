// Day 2 Exercise: JavaScript — promises, async/await, fetch and error handling
// JSONPlaceholder API with comprehensive error handling and concurrent requests

async function fetchData(apiUrl) {
    const controller = new AbortController();
    const timeout = setTimeout(() => {
        controller.abort();
    }, 5000);

    console.log("Fetching data...");
    try {
        const response = await fetch(apiUrl, {
            signal: controller.signal
        });
        // Handles a non-2xx response distinctly
        if (!response.ok) throw new Error(`HTTPError: ${response.status}`);
        const data = await response.json();
        // Handles an empty response
        if (data.length === 0) {
            throw new Error("Empty response");
        }
        console.log(data);
    } catch (error) {
        // Distinct handling for different error types
        if (error.name === "AbortError") {
            console.error("Request timed out.");
        } else if (error.message.startsWith("HTTPError")) {
            console.error(error.message);
        } else {
            console.error("Network error: Could not reach the server.");
        }
        // Re-throw so Promise.allSettled can catch it
        throw error;
    } finally {
        clearTimeout(timeout);
    }
}

// Fetches three resources concurrently with Promise.allSettled
// and reports which one failed
async function fetchMultipleData(apiUrls) {
    console.log("Fetching multiple data...");

    const promises = apiUrls.map(fetchData);

    const results = await Promise.allSettled(promises);

    results.forEach((result, index) => {
        if (result.status === "fulfilled") {
            console.log(`Request ${index + 1} succeeded:`, result.value);
        } else {
            console.error(`Request ${index + 1} failed:`, result.reason);
        }
    });

    return results;
}

// Run the exercise
fetchMultipleData([
    "https://jsonplaceholder.typicode.com/posts/1",
    "https://jsonplaceholder.typicode.com/users/1",
    "https://jsonplaceholder.typicode.com/comments/154"
]);
