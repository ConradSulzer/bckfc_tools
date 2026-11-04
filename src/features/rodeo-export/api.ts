import { requestToolsApi } from '@/api/client';
import type { Event, EventTickets, EventYears, Preview, Settings } from './types';

export function getEventYears(): Promise<EventYears> {
  return requestToolsApi('events/years', { method: 'GET' });
}

export function getEvents(year: number): Promise<Event[]> {
  return requestToolsApi(`events?year=${year}`, { method: 'GET' });
}

export function getEventTickets(eventId: string): Promise<EventTickets> {
  return requestToolsApi(`events/${eventId}`, { method: 'GET' });
}

export function previewAnglerExport(eventId: string, settings: Settings): Promise<Preview> {
  return requestToolsApi(`events/${eventId}/preview`, { method: 'POST', body: settings });
}
