# Reseller → Agent Mapping — Design

Date: 2026-07-22  
Status: Approved — implement

## Goal

Admin (and limited Agent users) can map Resellers to Agents under the Customer menu.

## Rules

- One Reseller → at most one active Agent
- Assign: set `partner.resellers.agent_id`, deactivate prior `partner.agent_reseller_assignments`, create new active assignment
- Unassign allowed (`agent_id = null` + deactivate assignments) — especially for new register flow
- **Admin:** assign to any agent / unassign
- **Partner Agent user:** may only assign resellers **to themselves**

## UI

1. Reseller show: Assign Agent form + Unassign
2. New page **Customer → Reseller Mapping**: bulk assign/unassign with filters + checklist

## Architecture

- `ResellerMappingService` (transactional assign/unassign)
- Extend `ResellerController` (update mapping on show)
- `ResellerMappingController` for bulk page
- Menu + permission under Customer
- Reuse `AgentResellerAssignment` pattern from `PartnerConversionService`
