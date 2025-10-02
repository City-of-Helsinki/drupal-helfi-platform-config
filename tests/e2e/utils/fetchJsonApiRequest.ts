import { type APIRequestContext, expect, request as playwrightRequest } from '@playwright/test';

/**
 * Type definition for the JSON:API response structure
 */
export type JsonApiResponse<T> = {
  data: T[];
};

/**
 * Make a request to a Drupal JSON:API endpoint
 * @template T - The type of the data items in the response
 * @param baseURL - The base URL of the Drupal site
 * @param endpoint - The JSON:API endpoint (e.g., '/jsonapi/node/article')
 * @param params - Optional query parameters
 * @returns A promise that resolves to the JSON:API response data
 */
export async function fetchJsonApiRequest<T>(
  baseURL: string,
  endpoint: string,
  params?: Record<string, string | number | boolean>,
): Promise<T> {
  // Create a new API context with the provided base URL
  // @todo: Should we use a valid certificate instead of
  //        ignoring the certificate?
  const api = await playwrightRequest.newContext({
    baseURL,
    ignoreHTTPSErrors: true,
  });

  // Make the request and ensure the API context is properly disposed.
  try {
    return await fetchRequest<T>(api, endpoint, params);
  } finally {
    await api.dispose();
  }
}

/**
 * Make a GET request to the specified endpoint and
 * return the JSON response.
 */
export async function fetchRequest<T>(
  request: APIRequestContext,
  endpoint: string,
  params?: Record<string, string | number | boolean>,
): Promise<T> {
  // Make the GET request and handle the response.
  const response = await request.get(endpoint, { params });

  // Verify the response was successful.
  const isOk = response.ok();
  expect(
    isOk,
    isOk ? undefined : `GET ${endpoint} failed with status ${response.status()} ${response.statusText()}`,
  ).toBeTruthy();

  // Parse and return the JSON response.
  return (await response.json()) as T;
}
