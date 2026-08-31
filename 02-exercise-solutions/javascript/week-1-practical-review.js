const urls = [
  "https://jsonplaceholder.typicode.com/posts/1",
  "https://jsonplaceholder.typicode.com/users/1",
  "https://jsonplaceholder.typicode.com/comments/1"
];

async function fetchData(url) {
  const controller = new AbortController();

  setTimeout(() => controller.abort(), 5000);

  try {
    const response = await fetch(url, {
      signal: controller.signal
    });

    // Non-2xx response
    if (!response.ok) {
      return `HTTP error: ${response.status}`;
    }

    const data = await response.json();

    // Valid but empty
    if (!data || Object.keys(data).length === 0) {
      return "Result is empty";
    }

    return data;

  } catch (error) {
    // Network failure or timeout
    return `Network error: ${error.message}`;
  }
}

async function main() {
  const results = await Promise.all(
    urls.map(url => fetchData(url))
  );

  console.log(results);
}

main();